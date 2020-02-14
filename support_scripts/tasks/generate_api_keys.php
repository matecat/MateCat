<?php 

$root = realpath(dirname(__FILE__) . '/../../');
include_once $root . "/inc/Bootstrap.php";
Bootstrap::start();

$db = Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);
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

$dao = new Users_UserDao( Database::obtain() ) ; 
$result = $dao->read( new Users_UserStruct(array('email' => $options['email']))); 
$user = $result[0]; 

$dao = new ApiKeys_ApiKeyDao( Database::obtain() ); 

$values = array(
  'uid' => $user->uid, 
  'api_key' => Utils::randomString( 20, true ),
  'api_secret' => Utils::randomString( 20, true ),
  'enabled' => true
);

$insert = $dao->create( new ApiKeys_ApiKeyStruct( $values ) ); 

echo "News keys added to $user->email:\n"; 
echo "\n" ; 
echo "API KEY:       $insert->api_key \n"; 
echo "API SECRET:    $insert->api_secret \n" ; 
echo "ENABLED:       $insert->enabled \n" ; 
echo "\n" ; 
