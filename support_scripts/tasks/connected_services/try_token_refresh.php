<?php


use Model\ConnectedServices\ConnectedServiceDao;
use Model\ConnectedServices\ConnectedServiceStruct;
use Model\ConnectedServices\GDrive\GDriveTokenVerifyModel;
use Model\ConnectedServices\Oauth\Google\GoogleProvider;
use Utils\Registry\AppConfig;

$root = realpath( dirname( __FILE__ ) . '/../../../' );
include_once $root . "/lib/Bootstrap.php";
Bootstrap::start();

$db        = \Bootstrap::getDatabase();
$db->debug = false;
$db->connect();

# Generate and insert a new enabled api keys pair for
# the give email, and print the result on screen .

function usage() {
    echo "Usage: \n
--id_service 42      The if the token to refresh \n";
    exit;
}

$options = getopt( 'h', [ 'id_service:' ] );

if ( array_key_exists( 'h', $options ) ) {
    usage();
}
if ( empty( $options ) ) {
    usage();
}
if ( !array_key_exists( 'id_service', $options ) ) {
    usage();
}


$dao     = new ConnectedServiceDao($db);
$service = $dao->fetchById( (int)$options[ 'id_service' ], ConnectedServiceStruct::class ) ?? throw new Exception( "service not found" );

//FIX
$client = (new GoogleProvider)->getClient( AppConfig::$HTTPHOST . "/gdrive/oauth/response" );

$verifier = new GDriveTokenVerifyModel( $service );
$verifier->validOrRefreshed( $client );
var_dump( $verifier );

