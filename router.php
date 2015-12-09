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

// This is unreleased APIs. I'm no longer fond of the [i:id_job] in the path,
// so before releasing change it use a querystring.
//
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

$klein->respond('GET', '/api/v2/project-completion-status/[i:id_project]', function() {
    $reflect  = new ReflectionClass('\API\V2\ProjectCompletionStatus');
    $instance = $reflect->newInstanceArgs(func_get_args());
    $instance->respond('status');
});

$klein->respond('GET', '/api/v2/project-translation/[i:id_project]', function() {
    $reflect  = new ReflectionClass('API_V2_ProjectTranslation');
    $instance = $reflect->newInstanceArgs(func_get_args());
    $instance->respond('status');
});

$klein->respond('GET', '/api/v2/jobs/[:id_job]/[:password]/segments/[:id_segment]/versions', function() {
    $reflect  = new ReflectionClass('\API\V2\SegmentVersion');
    $instance = $reflect->newInstanceArgs(func_get_args());
    $instance->respond('index');
});

$klein->respond('GET', '/api/v2/jobs/[:id_job]/[:password]/segments/[:id_segment]/versions/[:version_number]', function() {
    $reflect  = new ReflectionClass('API_V2_SegmentVersion');
    $instance = $reflect->newInstanceArgs(func_get_args());
    $instance->respond('detail');
});

$klein->respond('POST', '/api/v2/jobs/[:id_job]/[:password]/segments/[:id_segment]/errors', function() {
    $reflect  = new ReflectionClass('API\V2\SegmentTranslationError');
    $instance = $reflect->newInstanceArgs(func_get_args());
    $instance->respond('create');
});

$klein->dispatch();
