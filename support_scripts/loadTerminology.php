<?php


include '/var/www/cattool/inc/config.inc.php';
@INIT::obtain();
include_once INIT::$UTILS_ROOT . '/Utils.php';
include_once INIT::$UTILS_ROOT . '/Log.php';
include_once INIT::$MODEL_ROOT . '/Database.class.php';
include_once INIT::$MODEL_ROOT . '/queries.php';
include_once INIT::$UTILS_ROOT . '/engines/engine.class.php';
include_once INIT::$UTILS_ROOT . '/engines/tms.class.php';
include_once INIT::$UTILS_ROOT . '/engines/mt.class.php';
$db = Database::obtain ( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
$db->debug = INIT::$DEBUG;
$db->connect ();


$config = TMS::getConfigStruct();

$config[ 'source_lang' ]   = "nl-NL";
$config[ 'target_lang' ]   = "de-DE";
$config[ 'email' ]         = "demo@matecat.com";
$config[ 'get_mt' ]        = false;
$config[ 'id_user' ]       = "MyMemory_aec4b5ed";
//$config[ 'mt_only' ]       = false;
$config[ 'num_result' ]    = null;
//$config[ 'isConcordance' ] = false;
$config[ 'isGlossary' ]    = true;


//$fObject = new SplFileObject( 'Glossary_en_it.csv' );
$fObject = new SplFileObject( 'GlossaryMC NL-DE.csv' );
$fObject->setFlags( SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD );
$fObject->setCsvControl( "|", '"' );


$tms = new TMS( 1 );

foreach( $fObject as $k => $row ){
    $config[ 'segment' ]       = $row[0];
    $config[ 'translation' ]   = $row[1];
    $config[ 'tnote' ]         = ( isset($row[2]) ? $row[2] : null );

    $tms->set( $config );

    echo print_r($config,true) . "\n";
}




//print_r( $tms->get( $config ) );