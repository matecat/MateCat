<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 19/06/15
 * Time: 15.57
 * 
 */

require_once 'Config/Lite.php';

$rootPath = getenv( 'MATECAT_HOME' );
$matecatBranch = getenv( 'MATECAT_BRANCH' );


$newVersion  = @$argv[1];

function _TimeStampMsg($msg) {
    echo "[" . date( DATE_RFC822 ) . "] " . $msg;
}

$iniHandler = new Config_Lite( $rootPath . DIRECTORY_SEPARATOR . 'inc/config.ini.sample', LOCK_EX );

if( !empty( $newVersion ) ){
    $iniHandler->set( null, 'BUILD_NUMBER', $newVersion );
} else {
    $default_version = $iniHandler->get( null, 'BUILD_NUMBER' );
    $iniHandler->set( null, 'BUILD_NUMBER', $default_version );
}

if( $matecatBranch == 'master' ) {

    $iniHandler->set( null, 'ENV', 'production' );
    $iniHandler->set( null, 'CHECK_FS', true );

    $iniHandler->set( 'production', 'DB_SERVER', '10.30.1.250' );
    $iniHandler->set( 'production', 'DB_DATABASE', 'matecat_sandbox' );
    $iniHandler->set( 'production', 'DB_USER', 'matecat' );
    $iniHandler->set( 'production', 'DB_PASS', 'matecat01' );

    $iniHandler->set( 'production', 'CONVERSION_ENABLED', true );
    $iniHandler->set( 'production', 'FORCE_XLIFF_CONVERSION', true );
    $iniHandler->set( 'production', 'DQF_ENABLED', true );

    $iniHandler->set( 'production', 'REDIS_SERVERS', 'tcp://52.6.69.62:6379' );
    $iniHandler->set( 'production', 'QUEUE_BROKER_ADDRESS', "tcp://52.6.69.62:61613" );
    $iniHandler->set( 'production', 'QUEUE_DQF_ADDRESS', "tcp://52.6.69.62:61613" );
    $iniHandler->set( 'production', 'QUEUE_JMX_ADDRESS', "http://52.6.69.62:8161" );

    $iniHandler->set( 'production', 'STORAGE_DIR', "/storage" );

} else {

    $iniHandler->set( null, 'ENV', 'development' );
    $iniHandler->set( null, 'CHECK_FS', true );
    $iniHandler->set( 'development', 'CONVERSION_ENABLED', true );
    $iniHandler->set( 'development', 'FORCE_XLIFF_CONVERSION', true );
    $iniHandler->set( 'development', 'DQF_ENABLED', true );

}

try {
    $iniHandler->setFilename( $rootPath . DIRECTORY_SEPARATOR . 'inc/config.ini' );
    $iniHandler->save();
} catch (Config_Lite_Exception $e) {
    _TimeStampMsg( 'Failed to save INI' . "\n" );
    _TimeStampMsg( $e->getMessage() . "\n" );
    _TimeStampMsg( $e->getTraceAsString() . "\n" );
}