<?php


use Model\ConnectedServices\Oauth\OauthTokenEncryption;
use Model\Users\UserDao;
use Utils\Registry\AppConfig;

$root = realpath(dirname(__FILE__) . '/../../');
include_once $root . "/lib/Bootstrap.php";
Bootstrap::start();

$db = \Bootstrap::getDatabase();
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

$dao = new UserDao( $db ) ;
$user = $dao->getByEmail( $options['email'] ) ;

$oauthTokenEncryption = OauthTokenEncryption::getInstance();
$accessToken = $oauthTokenEncryption->decrypt( $user->oauth_access_token );

echo( $accessToken );

echo "\n" ;
