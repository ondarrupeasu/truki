<?php
/* API de Truki (Fase 1). Rutas via ?a=accion — funciona en cualquier hosting. */

require __DIR__.'/helpers.php';
require __DIR__.'/db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$pdo = db();
$a = $_GET['a'] ?? '';

try {
switch ($a) {

  /* ---------- REGISTRO ---------- */
  case 'register': {
    $u = trim((string) inp('username'));
    $p = (string) inp('password');
    if (mb_strlen($u) < 3 || mb_strlen($u) > 40) fail('El usuario debe tener entre 3 y 40 caracteres.');
    if (!preg_match('/^[\w.\-]+$/u', $u))         fail('El usuario solo admite letras, números y . _ -');
    if (mb_strlen($p) < 4)                        fail('La contraseña debe tener al menos 4 caracteres.');
    $s = $pdo->prepare("SELECT id FROM users WHERE username = ?"); $s->execute([$u]);
    if ($s->fetch()) fail('Ese usuario ya existe, elige otro.');
    $pdo->prepare("INSERT INTO users(username,pass_hash,insti,points,created_at) VALUES(?,?,?,?,?)")
        ->execute([$u, password_hash($p, PASSWORD_DEFAULT), 'IES Solokoetxe', 0, now()]);
    $id = (int) $pdo->lastInsertId();
    $tok = newtoken();
    $pdo->prepare("INSERT INTO tokens(token,user_id,created_at) VALUES(?,?,?)")->execute([$tok,$id,now()]);
    json_out(['token'=>$tok, 'user'=>pubuser(userrow($pdo,$id))]);
  }

  /* ---------- LOGIN ---------- */
  case 'login': {
    $u = trim((string) inp('username'));
    $p = (string) inp('password');
    $s = $pdo->prepare("SELECT * FROM users WHERE username = ?"); $s->execute([$u]); $row = $s->fetch();
    if (!$row || !password_verify($p, $row['pass_hash'])) fail('Usuario o contraseña incorrectos.', 401);
    $tok = newtoken();
    $pdo->prepare("INSERT INTO tokens(token,user_id,created_at) VALUES(?,?,?)")->execute([$tok,$row['id'],now()]);
    json_out(['token'=>$tok, 'user'=>pubuser($row)]);
  }

  /* ---------- MI PERFIL / ESTADO ---------- */
  case 'me': {
    $u = require_user($pdo);
    $it = $pdo->prepare("SELECT i.*, u.username FROM items i JOIN users u ON u.id=i.user_id WHERE i.user_id=? ORDER BY i.id DESC");
    $it->execute([$u['id']]);
    $tx = $pdo->prepare("SELECT delta,reason,created_at FROM txns WHERE user_id=? ORDER BY id DESC LIMIT 30");
    $tx->execute([$u['id']]);
    $mc = $pdo->prepare("SELECT COUNT(*) c FROM matches WHERE (user1=? OR user2=?) AND status='done'");
    $mc->execute([$u['id'],$u['id']]);
    json_out([
      'user'   => pubuser($u),
      'items'  => array_map('pubitem', $it->fetchAll()),
      'txns'   => $tx->fetchAll(),
      'trukis' => (int) $mc->fetch()['c'],
    ]);
  }

  /* ---------- SUBIR PRENDA (con foto) ---------- */
  case 'upload': {
    $u = require_user($pdo);
    $name = trim((string) ($_POST['name'] ?? ''));
    $cat  = (string) ($_POST['category'] ?? '');
    $cond = (string) ($_POST['cond'] ?? '');
    if ($name === '') fail('Ponle un nombre a la prenda.');
    $pts = points_for($cat, $cond);
    if ($pts === null) fail('Categoría o estado no válidos.');
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) fail('Sube una foto de la prenda.');
    $f = $_FILES['photo'];
    $c = cfg();
    if ($f['size'] > $c['max_upload']) fail('La foto pesa demasiado (máx. 5 MB).');
    $info = @getimagesize($f['tmp_name']);
    if (!$info) fail('El archivo no es una imagen válida.');
    $ext = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$info['mime']] ?? null;
    if (!$ext) fail('Formato no soportado (usa JPG, PNG o WEBP).');
    @mkdir($c['upload_dir'], 0775, true);
    $fn = bin2hex(random_bytes(8)).'.'.$ext;
    if (!save_image($f['tmp_name'], $c['upload_dir'].'/'.$fn, $info, $ext)) fail('No se pudo guardar la foto.', 500);
    $pdo->prepare("INSERT INTO items(user_id,name,category,cond,photo,points,status,created_at) VALUES(?,?,?,?,?,?, 'active', ?)")
        ->execute([$u['id'], $name, $cat, $cond, $fn, $pts, now()]);
    json_out(['item' => item_by_id($pdo, (int)$pdo->lastInsertId())]);
  }

  /* ---------- FEED (prendas de otras personas de tu insti) ---------- */
  case 'feed': {
    $u = require_user($pdo);
    $s = $pdo->prepare(
      "SELECT i.*, u.username FROM items i JOIN users u ON u.id=i.user_id
       WHERE i.user_id <> ? AND i.status='active' AND u.insti = ?
         AND i.id NOT IN (SELECT item_id FROM swipes WHERE user_id = ?)
       ORDER BY i.id DESC LIMIT 50");
    $s->execute([$u['id'], $u['insti'], $u['id']]);
    json_out(['items' => array_map('pubitem', $s->fetchAll())]);
  }

  /* ---------- SWIPE (like / nope) → detecta match ---------- */
  case 'swipe': {
    $u = require_user($pdo);
    $item_id = (int) inp('item_id');
    $dir = inp('dir') === 'like' ? 'like' : 'nope';
    $s = $pdo->prepare("SELECT * FROM items WHERE id = ?"); $s->execute([$item_id]); $item = $s->fetch();
    if (!$item) fail('Prenda no encontrada.', 404);
    if ($item['user_id'] == $u['id']) fail('No puedes deslizar tu propia prenda.');
    $d = $pdo->prepare("SELECT id FROM swipes WHERE user_id=? AND item_id=?"); $d->execute([$u['id'],$item_id]);
    if (!$d->fetch())
      $pdo->prepare("INSERT INTO swipes(user_id,item_id,dir,created_at) VALUES(?,?,?,?)")
          ->execute([$u['id'],$item_id,$dir,now()]);

    $resp = ['ok' => true];
    if ($dir === 'like') {
      $owner = (int) $item['user_id'];
      // ¿la otra persona ha dado like a alguna prenda MÍA activa?
      $q = $pdo->prepare(
        "SELECT s.item_id FROM swipes s JOIN items i ON i.id=s.item_id
         WHERE s.user_id=? AND s.dir='like' AND i.user_id=? AND i.status='active' LIMIT 1");
      $q->execute([$owner, $u['id']]);
      $mine = $q->fetch();
      if ($mine) {
        $em = $pdo->prepare("SELECT * FROM matches WHERE (user1=? AND user2=?) OR (user1=? AND user2=?) LIMIT 1");
        $em->execute([$u['id'],$owner,$owner,$u['id']]);
        if (!$em->fetch()) {
          $pdo->prepare("INSERT INTO matches(user1,item1,user2,item2,status,created_at) VALUES(?,?,?,?, 'pending', ?)")
              ->execute([$u['id'], (int)$mine['item_id'], $owner, $item_id, now()]);
          $resp['match'] = match_view($pdo, (int)$pdo->lastInsertId(), $u['id']);
        }
      }
    }
    json_out($resp);
  }

  /* ---------- MIS MATCHES ---------- */
  case 'matches': {
    $u = require_user($pdo);
    $s = $pdo->prepare("SELECT id FROM matches WHERE user1=? OR user2=? ORDER BY id DESC");
    $s->execute([$u['id'],$u['id']]);
    $out = [];
    foreach ($s->fetchAll() as $r) { $mv = match_view($pdo, (int)$r['id'], $u['id']); if ($mv) $out[] = $mv; }
    json_out(['matches' => $out]);
  }

  /* ---------- COMPLETAR INTERCAMBIO ---------- */
  case 'trade': {
    $u = require_user($pdo);
    $mid  = (int) inp('match_id');
    $mode = inp('mode') === 'direct' ? 'direct' : 'cuadrar';
    $s = $pdo->prepare("SELECT * FROM matches WHERE id=?"); $s->execute([$mid]); $m = $s->fetch();
    if (!$m || ($m['user1']!=$u['id'] && $m['user2']!=$u['id'])) fail('Match no encontrado.', 404);
    if ($m['status'] === 'done') fail('Este intercambio ya está hecho.');
    $meItemId   = $m['user1']==$u['id'] ? $m['item1'] : $m['item2'];
    $themItemId = $m['user1']==$u['id'] ? $m['item2'] : $m['item1'];
    $themId     = $m['user1']==$u['id'] ? $m['user2'] : $m['user1'];
    $mi = item_by_id($pdo, $meItemId); $ti = item_by_id($pdo, $themItemId);
    if (!$mi || !$ti || $mi['status']==='traded' || $ti['status']==='traded') fail('Alguna prenda ya no está disponible.');
    $diff = $ti['points'] - $mi['points'];   // >0: recibo algo de más valor → pago
    if ($mode==='cuadrar' && $diff>0 && $u['points'] < $diff)
      fail('Te faltan '.($diff-$u['points']).' trukipuntos para cuadrar.', 402);

    $pdo->beginTransaction();
    try {
      if ($mode==='cuadrar' && $diff!==0) {
        $them = username_by_id($pdo,$themId);
        adjust($pdo, $u['id'],  -$diff, 'Cambio con '.($them['username']??'?'));
        adjust($pdo, $themId,   +$diff, 'Cambio con '.$u['username']);
      }
      $pdo->prepare("UPDATE items SET user_id=?, status='traded' WHERE id=?")->execute([$themId, $meItemId]);
      $pdo->prepare("UPDATE items SET user_id=?, status='traded' WHERE id=?")->execute([$u['id'], $themItemId]);
      $pdo->prepare("UPDATE matches SET status='done' WHERE id=?")->execute([$mid]);
      $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    json_out(['ok'=>true, 'mode'=>$mode, 'diff'=>$diff, 'points'=>(int)userrow($pdo,$u['id'])['points']]);
  }

  /* ---------- COMPRAR TRUKIPUNTOS (simulado) ---------- */
  case 'buy': {
    $u = require_user($pdo);
    $packs = [50=>'2,99 €', 120=>'5,99 €', 300=>'12,99 €', 700=>'24,99 €'];
    $amt = (int) inp('amount');
    if (!isset($packs[$amt])) fail('Pack no válido.');
    adjust($pdo, $u['id'], $amt, 'Compra simulada · '.$amt.' pts ('.$packs[$amt].')');
    json_out(['ok'=>true, 'points'=>(int)userrow($pdo,$u['id'])['points']]);
  }

  /* ---------- EDITAR PRENDA (nombre/categoría/estado + foto opcional) ---------- */
  case 'item_update': {
    $u = require_user($pdo);
    $id = (int) ($_POST['id'] ?? inp('id'));
    $s = $pdo->prepare("SELECT * FROM items WHERE id=?"); $s->execute([$id]); $it = $s->fetch();
    if (!$it) fail('Prenda no encontrada.', 404);
    if ($it['user_id'] != $u['id']) fail('Esa prenda no es tuya.', 403);
    if ($it['status'] === 'traded') fail('Esa prenda ya se intercambió, no se puede editar.');
    $name = trim((string) ($_POST['name'] ?? $it['name']));
    $cat  = (string) ($_POST['category'] ?? $it['category']);
    $cond = (string) ($_POST['cond'] ?? $it['cond']);
    if ($name === '') fail('La prenda necesita un nombre.');
    $pts = points_for($cat, $cond);
    if ($pts === null) fail('Categoría o estado no válidos.');
    $photo = $it['photo'];
    $c = cfg();
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
      $f = $_FILES['photo'];
      if ($f['size'] > $c['max_upload']) fail('La foto pesa demasiado (máx. 5 MB).');
      $info = @getimagesize($f['tmp_name']);
      if (!$info) fail('El archivo no es una imagen válida.');
      $ext = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$info['mime']] ?? null;
      if (!$ext) fail('Formato no soportado (usa JPG, PNG o WEBP).');
      $fn = bin2hex(random_bytes(8)).'.'.$ext;
      if (!save_image($f['tmp_name'], $c['upload_dir'].'/'.$fn, $info, $ext)) fail('No se pudo guardar la foto.', 500);
      if ($photo && file_exists($c['upload_dir'].'/'.$photo)) @unlink($c['upload_dir'].'/'.$photo);
      $photo = $fn;
    }
    $pdo->prepare("UPDATE items SET name=?, category=?, cond=?, photo=?, points=? WHERE id=?")
        ->execute([$name, $cat, $cond, $photo, $pts, $id]);
    json_out(['item' => item_by_id($pdo, $id)]);
  }

  /* ---------- BORRAR PRENDA ---------- */
  case 'item_delete': {
    $u = require_user($pdo);
    $id = (int) inp('id');
    $s = $pdo->prepare("SELECT * FROM items WHERE id=?"); $s->execute([$id]); $it = $s->fetch();
    if (!$it) fail('Prenda no encontrada.', 404);
    if ($it['user_id'] != $u['id']) fail('Esa prenda no es tuya.', 403);
    if ($it['status'] === 'traded') fail('Esa prenda ya se intercambió, no se puede borrar.');
    $pdo->beginTransaction();
    try {
      $pdo->prepare("DELETE FROM swipes WHERE item_id=?")->execute([$id]);
      $pdo->prepare("DELETE FROM matches WHERE status='pending' AND (item1=? OR item2=?)")->execute([$id, $id]);
      $pdo->prepare("DELETE FROM items WHERE id=?")->execute([$id]);
      $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    $c = cfg();
    if (!empty($it['photo']) && file_exists($c['upload_dir'].'/'.$it['photo'])) @unlink($c['upload_dir'].'/'.$it['photo']);
    json_out(['ok' => true]);
  }

  default:
    fail('Acción desconocida: "'.$a.'"', 404);
}
} catch (Throwable $e) {
  fail('Error del servidor: '.$e->getMessage(), 500);
}
