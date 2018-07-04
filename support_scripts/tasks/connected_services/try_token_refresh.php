<?php

$root = realpath(dirname(__FILE__) . '/../../../');
include_once $root . "/inc/Bootstrap.php";
Bootstrap::start();

$db = Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);
$db->debug = false;
$db->connect();

# Generate and insert a new enabled api keys pair for
# the give email, and print the result on screen .

function usage() {
    echo "Usage: \n
--id_service 42      The if the token to refresh \n";
    exit;
}

$options = getopt( 'h', array( 'id_service:'));

if (array_key_exists('h', $options))          usage() ;
if (empty($options))                          usage() ;
if (!array_key_exists('id_service', $options))     usage() ;


$dao = new \ConnectedServices\ConnectedServiceDao() ;
$service = $dao->findById( $options['id_service'] ) ;

$verifier = new \ConnectedServices\GDriveTokenVerifyModel($service) ;
$verifier->validOrRefreshed() ;
var_dump( $verifier ) ;

