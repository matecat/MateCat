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

        array( 'file' => 'glossary.csv', 'source' => 'it-IT', 'target' => "en-GB" ),

);



foreach ( $glossaries as $gloss ) {


    $config = TMS::getConfigStruct();
    $config[ 'source_lang' ] = $gloss['source'];
    $config[ 'target_lang' ] = $gloss['target'];
    $config[ 'email' ]       = "demo@matecat.com";
    $config[ 'get_mt' ]      = false;
    $config[ 'id_user' ]     = "5c94c21ae1f3c907b3b8";
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