<?php
require __DIR__ . '/Bootstrap.php';
Bootstrap::start();

return array(
  'paths' => array(
    'migrations' => 'migrations'
  ),
  'environments' => array(
    'default_migration_table' => 'phinxlog',
    'default_database' => 'auto',
    'auto' => array(
      'adapter' => 'mysql',
      'name' => INIT::$DB_DATABASE,
      'user' => INIT::$DB_USER,
      'pass' => INIT::$DB_PASS,
      'host' => INIT::$DB_SERVER,
    )
  )
);
