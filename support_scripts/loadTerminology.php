<?php


include '/var/www/cattool/inc/config.inc.php';
@INIT::obtain();
include_once INIT::$UTILS_ROOT . '/utils.class.php';
include_once INIT::$UTILS_ROOT . '/log.class.php';
include_once INIT::$MODEL_ROOT . '/Database.class.php';
include_once INIT::$MODEL_ROOT . '/queries.php';
include_once INIT::$UTILS_ROOT . '/engines/engine.class.php';
include_once INIT::$UTILS_ROOT . '/engines/tms.class.php';
include_once INIT::$UTILS_ROOT . '/engines/mt.class.php';
$db = Database::obtain ( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
$db->debug = INIT::$DEBUG;
$db->connect ();


$config = TMS::getConfigStruct();

$config[ 'source_lang' ]   = "en-US";
$config[ 'target_lang' ]   = "it-IT";
$config[ 'email' ]         = "demo@matecat.com";
//$config[ 'get_mt' ]        = true;
$config[ 'id_user' ]       = "MyMemory_4b3bcd17c1";
//$config[ 'mt_only' ]       = false;
//$config[ 'num_result' ]    = 2;
//$config[ 'isConcordance' ] = false;
$config[ 'isGlossary' ]    = true;


//$fObject = new SplFileObject( 'Glossary_en_it.csv' );
$fObject = new SplFileObject( 'Fake_Glossary.csv' );
$fObject->setFlags( SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE | SplFileObject::READ_AHEAD );
$fObject->setCsvControl( ";", '"' );


$tms = new TMS( 1 );

foreach( $fObject as $k => $row ){
    $config[ 'segment' ]       = $row[0];
    $config[ 'translation' ]   = $row[1];
    $config[ 'tnote' ]         = $row[2];

    $tms->set( $config );

    echo print_r($config,true) . "\n";
}




//print_r( $tms->get( $config ) );