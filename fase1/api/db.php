<?php
/* Conexión PDO + creación de tablas (funciona igual en SQLite y MySQL). */

function db(){
  static $pdo = null;
  if ($pdo) return $pdo;
  $c = cfg();
  if ($c['driver'] === 'sqlite') {
    @mkdir(dirname($c['sqlite']), 0775, true);
    $pdo = new PDO('sqlite:' . $c['sqlite']);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA foreign_keys=ON');
  } else {
    $m = $c['mysql'];
    $dsn = "mysql:host={$m['host']};dbname={$m['name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $m['user'], $m['pass']);
  }
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  init_schema($pdo, $c['driver']);
  return $pdo;
}

function init_schema($pdo, $driver){
  $auto = $driver === 'sqlite'
    ? 'INTEGER PRIMARY KEY AUTOINCREMENT'
    : 'INT AUTO_INCREMENT PRIMARY KEY';

  $pdo->exec("CREATE TABLE IF NOT EXISTS users(
    id $auto,
    username   VARCHAR(40) UNIQUE NOT NULL,
    pass_hash  VARCHAR(255) NOT NULL,
    insti      VARCHAR(60) DEFAULT 'IES Solokoetxe',
    points     INT DEFAULT 0,
    created_at INT
  )");

  $pdo->exec("CREATE TABLE IF NOT EXISTS tokens(
    token      VARCHAR(64) PRIMARY KEY,
    user_id    INT NOT NULL,
    created_at INT
  )");

  $pdo->exec("CREATE TABLE IF NOT EXISTS items(
    id $auto,
    user_id    INT NOT NULL,
    name       VARCHAR(80) NOT NULL,
    category   VARCHAR(20) NOT NULL,
    cond       VARCHAR(20) NOT NULL,
    photo      VARCHAR(120),
    points     INT NOT NULL,
    status     VARCHAR(12) DEFAULT 'active',
    created_at INT
  )");

  $pdo->exec("CREATE TABLE IF NOT EXISTS swipes(
    id $auto,
    user_id    INT NOT NULL,
    item_id    INT NOT NULL,
    dir        VARCHAR(6) NOT NULL,
    created_at INT
  )");

  $pdo->exec("CREATE TABLE IF NOT EXISTS matches(
    id $auto,
    user1 INT NOT NULL, item1 INT NOT NULL,
    user2 INT NOT NULL, item2 INT NOT NULL,
    status     VARCHAR(10) DEFAULT 'pending',
    created_at INT
  )");

  $pdo->exec("CREATE TABLE IF NOT EXISTS txns(
    id $auto,
    user_id    INT NOT NULL,
    delta      INT NOT NULL,
    reason     VARCHAR(120),
    created_at INT
  )");

  run_migrations($pdo, $driver);
}

/* Migraciones versionadas: cada bloque se ejecuta UNA sola vez. */
function run_migrations($pdo, $driver){
  $pdo->exec("CREATE TABLE IF NOT EXISTS schema_meta(k VARCHAR(32) PRIMARY KEY, v VARCHAR(64))");
  $ver = 0;
  try { $r = $pdo->query("SELECT v FROM schema_meta WHERE k='version'")->fetch(); if ($r) $ver = (int) $r['v']; }
  catch (Throwable $e) {}

  if ($ver < 1) {
    ensure_column($pdo, 'users', 'avatar', 'VARCHAR(160)');
    // Los emojis (4 bytes) necesitan utf8mb4; la BD venía en utf8mb3.
    if ($driver !== 'sqlite') {
      foreach (['users','items','swipes','matches','txns','tokens'] as $t) {
        try { $pdo->exec("ALTER TABLE $t CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"); }
        catch (Throwable $e) {}
      }
    }
    set_schema_version($pdo, 1);
  }

  if ($ver < 2) {
    ensure_column($pdo, 'users', 'is_admin', 'INT DEFAULT 0');
    set_schema_version($pdo, 2);
  }
}
function set_schema_version($pdo, $v){
  try { $pdo->prepare("INSERT INTO schema_meta(k,v) VALUES('version',?)")->execute([(string) $v]); }
  catch (Throwable $e) { $pdo->prepare("UPDATE schema_meta SET v=? WHERE k='version'")->execute([(string) $v]); }
}
/* Añade una columna si no existe (portable MySQL/SQLite). */
function ensure_column($pdo, $table, $col, $def){
  try { $pdo->exec("ALTER TABLE $table ADD COLUMN $col $def"); }
  catch (Throwable $e) { /* la columna ya existe */ }
}
