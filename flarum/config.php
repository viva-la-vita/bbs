<?php return array (
  'debug' => true,
  'database' => 
  array (
    'driver' => 'mysql',
    'host' => 'site-db',
    'port' => 3306,
    'database' => 'flarum',
    'username' => 'flarum',
    'password' => 'nyaxzRuEhKPe',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'strict' => false,
    'engine' => 'InnoDB',
    'prefix_indexes' => true,
  ),
  'url' => 'https://forum.viva-la-vita.org',
  'paths' => 
  array (
    'api' => 'api',
    'admin' => 'admin',
  ),
  (new FoF\Upload\Extend\Adapters())
        ->force('aws-s3'),
);