<?php
/* Utilidades compartidas: config, respuestas JSON, auth por token, valoración, imágenes. */

function cfg(){ static $c = null; if ($c === null) $c = require __DIR__.'/config.php'; return $c; }
function now(){ return time(); }
function newtoken(){ return bin2hex(random_bytes(24)); }

function json_out($data, $code = 200){
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}
function fail($msg, $code = 400){ json_out(['error' => $msg], $code); }

/* Cuerpo de la petición: JSON o formulario */
function body(){
  static $b = null;
  if ($b !== null) return $b;
  $raw = file_get_contents('php://input');
  $j = json_decode($raw, true);
  $b = is_array($j) ? $j : $_POST;
  return $b;
}
function inp($k, $d = null){
  $b = body();
  if (isset($b[$k]))    return $b[$k];
  if (isset($_POST[$k])) return $_POST[$k];
  return $d;
}

/* --- Auth por token Bearer --- */
function bearer(){
  $h = null;
  if (isset($_SERVER['HTTP_AUTHORIZATION']))        $h = $_SERVER['HTTP_AUTHORIZATION'];
  elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) $h = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
  elseif (function_exists('getallheaders')) {
    foreach (getallheaders() as $k => $v) if (strtolower($k) === 'authorization') { $h = $v; break; }
  }
  if ($h && preg_match('/Bearer\s+(.+)/i', $h, $m)) return trim($m[1]);
  return $_GET['token'] ?? (body()['token'] ?? null);
}
function current_user($pdo){
  $t = bearer();
  if (!$t) return null;
  $s = $pdo->prepare("SELECT u.* FROM tokens t JOIN users u ON u.id = t.user_id WHERE t.token = ?");
  $s->execute([$t]);
  return $s->fetch() ?: null;
}
function require_user($pdo){
  $u = current_user($pdo);
  if (!$u) fail('No autenticado. Inicia sesión.', 401);
  return $u;
}

/* --- Valoración: base por categoría × estado --- */
function points_for($cat, $cond){
  $base = ['accesorio'=>10,'camiseta'=>15,'camisa'=>25,'pantalon'=>30,'sudadera'=>40,'abrigo'=>50];
  $mult = ['nuevo'=>1.5,'comonuevo'=>1.2,'bueno'=>1.0,'usado'=>0.7];
  if (!isset($base[$cat]) || !isset($mult[$cond])) return null;
  return (int) round($base[$cat] * $mult[$cond]);
}

/* --- Vistas públicas (nunca exponen pass_hash) --- */
function pubuser($u){
  $c = cfg();
  $av = $u['avatar'] ?? '';
  $avatar = ['type'=>'none', 'value'=>''];
  if ($av !== '' && $av !== null) {
    if (strncmp($av, 'img:', 4) === 0) $avatar = ['type'=>'img', 'value'=>$c['upload_url'].'/'.substr($av, 4)];
    else $avatar = ['type'=>'emoji', 'value'=>$av];
  }
  return ['id'=>(int)$u['id'],'username'=>$u['username'],'insti'=>$u['insti'],'points'=>(int)$u['points'],'avatar'=>$avatar];
}
function pubitem($r){
  $c = cfg();
  return [
    'id'=>(int)$r['id'], 'name'=>$r['name'], 'category'=>$r['category'], 'cond'=>$r['cond'],
    'points'=>(int)$r['points'], 'status'=>$r['status'],
    'photo'=> !empty($r['photo']) ? $c['upload_url'].'/'.$r['photo'] : null,
    'owner'=> $r['username'] ?? null,
  ];
}

/* --- Acceso a filas --- */
function userrow($pdo, $id){ $s=$pdo->prepare("SELECT * FROM users WHERE id=?"); $s->execute([$id]); return $s->fetch(); }
function username_by_id($pdo, $id){ $s=$pdo->prepare("SELECT username FROM users WHERE id=?"); $s->execute([$id]); return $s->fetch(); }
function item_by_id($pdo, $id){
  $s=$pdo->prepare("SELECT i.*, u.username FROM items i JOIN users u ON u.id=i.user_id WHERE i.id=?");
  $s->execute([$id]); $r=$s->fetch(); return $r ? pubitem($r) : null;
}

/* --- Ajuste de puntos + registro del movimiento --- */
function adjust($pdo, $uid, $delta, $reason){
  $pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?")->execute([$delta, $uid]);
  $pdo->prepare("INSERT INTO txns(user_id,delta,reason,created_at) VALUES(?,?,?,?)")
      ->execute([$uid, $delta, $reason, now()]);
}

/* --- Construir la vista de un match desde la perspectiva de $me --- */
function match_view($pdo, $mid, $me){
  $s=$pdo->prepare("SELECT * FROM matches WHERE id=?"); $s->execute([$mid]); $m=$s->fetch();
  if (!$m) return null;
  $meItem   = $m['user1']==$me ? $m['item1'] : $m['item2'];
  $themItem = $m['user1']==$me ? $m['item2'] : $m['item1'];
  $themUser = $m['user1']==$me ? $m['user2'] : $m['user1'];
  $ou = username_by_id($pdo, $themUser);
  return [
    'id'=>(int)$m['id'], 'status'=>$m['status'],
    'my_item'=>item_by_id($pdo,$meItem),
    'their_item'=>item_by_id($pdo,$themItem),
    'other'=> $ou ? $ou['username'] : '¿?',
  ];
}

/* --- Guardar imagen (redimensiona con GD si está disponible) --- */
function save_image($src, $dest, $info, $ext){
  $max = 1000;
  if (function_exists('imagecreatetruecolor') && !empty($info[0])) {
    [$w, $h] = $info;
    $scale = min(1, $max / max($w, $h));
    if ($scale < 1) {
      $srcImg = $ext==='jpg' ? @imagecreatefromjpeg($src)
              : ($ext==='png' ? @imagecreatefrompng($src)
              : @imagecreatefromwebp($src));
      if ($srcImg) {
        $nw = (int)($w*$scale); $nh = (int)($h*$scale);
        $dst = imagecreatetruecolor($nw, $nh);
        if ($ext==='png') { imagealphablending($dst,false); imagesavealpha($dst,true); }
        imagecopyresampled($dst,$srcImg,0,0,0,0,$nw,$nh,$w,$h);
        if ($ext==='jpg')      imagejpeg($dst,$dest,82);
        elseif ($ext==='png')  imagepng($dst,$dest,6);
        else                   imagewebp($dst,$dest,82);
        imagedestroy($dst); imagedestroy($srcImg);
        return true;
      }
    }
  }
  return is_uploaded_file($src) ? move_uploaded_file($src,$dest) : copy($src,$dest);
}
