<?php return array (
  'debug' => false,
  'database' =>
  array (
    'driver' => 'mysql',
    'host' => 'mysql',
    'port' => 3306,
    'database' => getenv("MYSQL_DATABASE"),
    'username' => getenv("MYSQL_USER"),
    'password' => getenv("MYSQL_PASSWORD"),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'strict' => false,
    'engine' => NULL,
    'prefix_indexes' => true,
  ),
  'url' => getenv("URL"),
  'paths' =>
  array (
    'api' => 'api',
    'admin' => 'admin',
  ),
  'headers' =>
  array (
    'poweredByHeader' => true,
    'referrerPolicy' => 'same-origin',
  ),
);
