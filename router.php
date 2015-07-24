<?php

require_once 'vendor/autoload.php';

require_once './inc/Bootstrap.php' ;
require_once './lib/Model/queries.php' ;

Bootstrap::start();

// FIXME: apis use PDO in some case which requires a different connection
// object than the one instantiated by Database::obtain.
require_once 'lib/Model/PDOConnection.php';
PDOConnection::connectINIT();

$db = Database::obtain ( INIT::$DB_SERVER, INIT::$DB_USER,
    INIT::$DB_PASS, INIT::$DB_DATABASE );
$db->connect ();

Log::$uniqID = uniqid() ;

$klein = new \Klein\Klein();

$klein->respond('GET', '/api/v2/jobs/[i:id_job]/revision-data', function() {
    $reflect  = new ReflectionClass('API_V2_JobRevisionData');
    $instance = $reflect->newInstanceArgs(func_get_args());
    $instance->respond('revisionData');
});

$klein->respond('GET', '/api/v2/jobs/[i:id_job]/revision-data/segments', function() {
    $reflect  = new ReflectionClass('API_V2_JobRevisionData');
    $instance = $reflect->newInstanceArgs(func_get_args());
    $instance->respond('segments');
});

$klein->dispatch();
