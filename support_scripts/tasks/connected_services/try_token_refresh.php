<?php


use Model\ConnectedServices\Oauth\Google\GoogleProvider;
use Model\DataAccess\Database;
use Utils\Registry\AppConfig;

$root = realpath( dirname( __FILE__ ) . '/../../../' );
include_once $root . "/lib/Bootstrap.php";
Bootstrap::start();

$db        = Database::obtain( AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE );
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


$dao     = new \Model\ConnectedServices\ConnectedServiceDao();
$service = $dao->findById( $options[ 'id_service' ] );

//FIX
$client = GoogleProvider::getClient( AppConfig::$HTTPHOST . "/gdrive/oauth/response" );

$verifier = new \Model\ConnectedServices\GDrive\GDriveTokenVerifyModel( $service );
$verifier->validOrRefreshed( $client );
var_dump( $verifier );

