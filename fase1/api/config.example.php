<?php
/* Copia este archivo como config.php y rellena tus datos.
   En Infomaniak: driver 'mysql' + los datos de tu base de datos MySQL.
   En local (pruebas): driver 'sqlite' (no necesita instalar nada). */
return [
  'driver' => 'mysql',                 // 'mysql' en Infomaniak · 'sqlite' en local
  'mysql'  => [
    'host' => 'XXXXX.myd.infomaniak.com',   // el "servidor" que te da Infomaniak
    'name' => 'tu_base_de_datos',
    'user' => 'tu_usuario',
    'pass' => 'tu_contraseña',
  ],
  'sqlite'     => __DIR__ . '/../data/truki.sqlite',
  'upload_dir' => __DIR__ . '/../uploads',
  'upload_url' => 'uploads',           // ruta pública de las fotos
  'max_upload' => 5 * 1024 * 1024,     // 5 MB
];
