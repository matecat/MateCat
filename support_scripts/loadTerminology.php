<?php
$test = false;
$skip = true;

include '/var/www/cattool/inc/config.inc.php';
@Bootstrap::start();
include_once INIT::$MODEL_ROOT . '/queries.php';
include_once INIT::$UTILS_ROOT . '/Engines/engine.class.php';
include_once INIT::$UTILS_ROOT . '/Engines/tms.class.php';
include_once INIT::$UTILS_ROOT . '/Engines/mt.class.php';
$db = Database::obtain ( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
$db->debug = INIT::$DEBUG;
$db->connect ();


$glossaries = array(

        array( 'file' => '140905_Copy of 140825_Glossary_Anti-trust-law Chinese Rev1_V01JH_English-Chinese_Trad.csv', 'source' => "en-US", 'target' => "zh-CN" ),
//        array( 'file' => 'Nuov_Worksheet.csv', 'source' => 'en-US', 'target' => "da-DK" ),
//        array( 'file' => 'Nuov_Worksheet.csv', 'source' => 'en-US', 'target' => "nl-NL" ),
//        array( 'file' => 'Nuov_Worksheet.csv', 'source' => 'en-US', 'target' => "fr-FR" ),
//        array( 'file' => 'Nuov_Worksheet.csv', 'source' => 'en-US', 'target' => "de-DE" ),
//        array( 'file' => 'Nuov_Worksheet.csv', 'source' => 'en-US', 'target' => "pl-PL" ),
//        array( 'file' => 'Nuov_Worksheet.csv', 'source' => 'en-US', 'target' => "pt-PT" ),
//        array( 'file' => 'Nuov_Worksheet.csv', 'source' => 'en-US', 'target' => "ru-RU" ),
//        array( 'file' => 'Nuov_Worksheet.csv', 'source' => 'en-US', 'target' => "es-ES" ),
//        array( 'file' => 'Nuov_Worksheet.csv', 'source' => 'en-US', 'target' => "sv-SE" ),

);



foreach ( $glossaries as $gloss ) {


    $config = TMS::getConfigStruct();
    $config[ 'source_lang' ] = $gloss['source'];
    $config[ 'target_lang' ] = $gloss['target'];
    $config[ 'email' ]       = "demo@matecat.com";
    $config[ 'get_mt' ]      = false;
    $config[ 'id_user' ]     = "32466c3a2be6159e6ae6";
    $config[ 'num_result' ]  = null;
    $config[ 'isGlossary' ]  = true;


    $fObject = new SplFileObject( $gloss['file'] );
    $fObject->setFlags( SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE );
    $fObject->setCsvControl( ",", '"' );


    $tms = new TMS( 1 );

    foreach ( $fObject as $k => $row ) {

        if ( $test || $skip ) {
            if ( !isset( $row[ 1 ] ) || empty( $row[ 1 ] ) ) {
                echo "\nFailed at Row: ";
                print_r( ( $fObject->key() + 1 ) . "\n" );
                echo $row[ 0 ] . "\n";
//                echo $row[ 1 ] . "\n\n";
//                sleep(1);
                continue;
            }
        }

        $config[ 'segment' ]     = $row[ 0 ];
        $config[ 'translation' ] = $row[ 1 ];
        $config[ 'tnote' ]       = ( isset( $row[ 2 ] ) ? $row[ 2 ] : null );

        if ( !$test ) {
            $tms->set( $config );
            echo "SET\n";
        }

        echo print_r( $config, true ) . "\n";
    }


}

//print_r( $tms->get( $config ) );