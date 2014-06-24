<?php
$test = false;
$skip = true;

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


$glossaries = array(

        array( 'file' => 'GLOSSARIO_EN_ARA.csv', 'source' => 'en-GB', 'target' => 'ar-SA' ),
        array( 'file' => 'GLOSSARIO_EN_BUL.csv', 'source' => 'en-GB', 'target' => 'bg-BG' ),
        array( 'file' => 'GLOSSARIO_EN_CHI_SIMPL.csv', 'source' => 'en-GB', 'target' => 'zh-CN' ),
        array( 'file' => 'GLOSSARIO_EN_CHI_TRAD.csv', 'source' => 'en-GB', 'target' => 'zh-TW' ),
        array( 'file' => 'GLOSSARIO_EN_CZE.csv', 'source' => 'en-GB', 'target' => 'cs-CZ' ),
        array( 'file' => 'GLOSSARIO_EN_DUT.csv', 'source' => 'en-GB', 'target' => 'nl-NL' ),
        array( 'file' => 'GLOSSARIO_EN_FRE.csv', 'source' => 'en-GB', 'target' => 'fr-FR' ),
        array( 'file' => 'GLOSSARIO_EN_GER.csv', 'source' => 'en-GB', 'target' => 'de-DE' ),
        array( 'file' => 'GLOSSARIO_EN_HIN.csv', 'source' => 'en-GB', 'target' => 'hi-IN' ),
        array( 'file' => 'GLOSSARIO_EN_HUN.csv', 'source' => 'en-GB', 'target' => 'hu-HU' ),
        array( 'file' => 'GLOSSARIO_EN_IT.csv', 'source' => 'en-GB', 'target' => 'it-IT' ),
        array( 'file' => 'GLOSSARIO_EN_KOR.csv', 'source' => 'en-GB', 'target' => 'ko-KR' ),
        array( 'file' => 'GLOSSARIO_EN_MAY.csv', 'source' => 'en-GB', 'target' => 'ms-MY' ),
        array( 'file' => 'GLOSSARIO_EN_POL.csv', 'source' => 'en-GB', 'target' => 'pl-PL' ),
        array( 'file' => 'GLOSSARIO_EN_RUM.csv', 'source' => 'en-GB', 'target' => 'ro-RO' ),
        array( 'file' => 'GLOSSARIO_EN_SLO.csv', 'source' => 'en-GB', 'target' => 'sk-SK' ),
        array( 'file' => 'GLOSSARIO_EN_SPA.csv', 'source' => 'en-GB', 'target' => 'es-ES' ),
        array( 'file' => 'GLOSSARIO_EN_SPA_LATAM.csv', 'source' => 'en-GB', 'target' => 'es-MX' ),
        array( 'file' => 'GLOSSARIO_EN_SRP.csv', 'source' => 'en-GB', 'target' => 'sr-Latn-RS' ),
        array( 'file' => 'GLOSSARIO_EN_TAM.csv', 'source' => 'en-GB', 'target' => 'ta-IN' ),
        array( 'file' => 'GLOSSARIO_EN_TGL.csv', 'source' => 'en-GB', 'target' => 'tl-PH' ),
        array( 'file' => 'GLOSSARIO_EN_TUR.csv', 'source' => 'en-GB', 'target' => 'tr-TR' ),
        array( 'file' => 'GLOSSARIO_EN_UKR.csv', 'source' => 'en-GB', 'target' => 'uk-UA' ),

);



foreach ( $glossaries as $gloss ) {


    $config = TMS::getConfigStruct();
    $config[ 'source_lang' ] = $gloss['source'];
    $config[ 'target_lang' ] = $gloss['target'];
    $config[ 'email' ]       = "demo@matecat.com";
    $config[ 'get_mt' ]      = false;
    $config[ 'id_user' ]     = "3d995a93a1a7dbf987e9";
    $config[ 'num_result' ]  = null;
    $config[ 'isGlossary' ]  = true;


    $fObject = new SplFileObject( $gloss['file'] );
    $fObject->setFlags( SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE );
    $fObject->setCsvControl( "\t", '\'' );


    $tms = new TMS( 1 );

    foreach ( $fObject as $k => $row ) {

        if ( $test || $skip ) {
            if ( !isset( $row[ 1 ] ) || empty( $row[ 1 ] ) ) {
                echo "\nFailed at Row: ";
                print_r( ( $fObject->key() + 1 ) . "\n" );
                echo $row[ 0 ] . "\n";
                echo $row[ 1 ] . "\n\n";
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