<?php


use Model\DataAccess\Database;
use Model\Users\UserDao;
use Utils\Registry\AppConfig;

$root = realpath(dirname(__FILE__) . '/../../../');
include_once $root . "/lib/Bootstrap.php";
Bootstrap::start();

$db = Database::obtain(AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE);
$db->debug = false;
$db->connect();

# Generate and insert a new enabled api keys pair for
# the give email, and print the result on screen .

function usage() {
    echo "Usage: \n
--email foo@example.org       The email address of the user\n";

    exit;
}

$options = getopt( 'h', array( 'email:'));

if (array_key_exists('h', $options))          usage() ;
if (empty($options))                          usage() ;
if (!array_key_exists('email', $options))     usage() ;

$dao = new UserDao( Database::obtain() ) ;
$user = $dao->getByEmail( $options['email'] ) ;


if ( $user->oauth_access_token ) {
    echo "------------------------------------------------\n" ;
    echo "user oauth_access_token\n" ;
    echo "------------------------------------------------\n" ;
    echo "\n";

    var_dump ( $user->getDecodedOauthAccessToken() ) ;
}

$cs = new \Model\ConnectedServices\ConnectedServiceDao() ;
$services = $cs->findServicesByUser($user);

foreach ( $services as $service ) {

    echo "------------------------------------------------\n" ;
    echo "service oauth_access_token\n" ;
    echo $service->service . "\n";
    echo "------------------------------------------------\n" ;

    var_dump( $service->toArray() );

    echo $service->getDecryptedOauthAccessToken() ;
    echo "\n";

}


