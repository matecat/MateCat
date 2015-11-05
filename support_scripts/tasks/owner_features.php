<?php 

$root = realpath(dirname(__FILE__) . '/../../');
include_once $root . "/inc/Bootstrap.php";
Bootstrap::start();

$db = Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);
$db->debug = false;
$db->connect();

$valid_codes = array('project_completion');  // TODO move this elsewhere

# Generate and insert a new enabled api keys pair for
# the give email, and print the result on screen .

function usage() {
  global $valid_codes; 
  $valid_codes_string = implode(', ', $valid_codes);

  echo <<<END
Usage: 

--email   foo@example.org       The email address of the user
--feature  feature_code          Feature code to enable for the user

Valid feature codes are: $valid_codes_string

END;
  
  exit; 
}

function validateFeature($feature) {
  global $valid_codes; 
  echo $feature ; 
  return in_array($feature,  $valid_codes) ; 
}

$options = getopt( 'h', array( 'email:', 'feature:')); 

if ( array_key_exists('h', $options) )            usage() ; 
if ( empty($options) )                            usage() ;
if ( !array_key_exists('email', $options) )       usage() ; 
if ( !array_key_exists('feature', $options) )     usage() ; 
if ( !validateFeature( $options['feature']))      usage() ; 

$dao = new Users_UserDao( Database::obtain() ) ; 
$result = $dao->read( new Users_UserStruct(array('email' => $options['email']))); 
$user = $result[0]; 

$dao = new OwnerFeatures_OwnerFeatureDao( Database::obtain() ); 

$values = array(
  'uid'          => $user->uid,
  'feature_code' => $options['feature'],
  'options'      => '{}',
  'enabled'      => true
);

$insert = $dao->create( new OwnerFeatures_OwnerFeatureStruct( $values ) ); 

echo <<<END

Record added for user $user->email:

FEATURE CODE:       $insert->feature_code
ENABLED:            $insert->enabled

END;
