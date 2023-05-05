<?php

use TMS\TMSService;

/**
 * Created by JetBrains PhpStorm.
 * User: domenico
 * Date: 31/07/13
 * Time: 18.54
 * To change this template use File | Settings | File Templates.
 */

class ajaxUtilsController extends ajaxController {

    private $__postInput = null;

    public function __construct() {

        //SESSION ENABLED
        parent::sessionStart();
        parent::__construct();

        $posts = $_POST;
        foreach ( $posts as $key => &$value ) {
            $value = filter_var( $value, FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW ] );
        }

        $this->__postInput = $posts;

    }

    public function doAction() {

        switch ( $this->__postInput[ 'exec' ] ) {

            case 'ping':
                $db   = Database::obtain();
                $stmt = $db->getConnection()->prepare( "SELECT 1" );
                $stmt->execute();
                $this->result[ 'data' ] = [ "OK", time() ];
                break;
            case 'checkTMKey':
                //get MyMemory apiKey service

                $tmxHandler = new TMSService();

                //validate the key
                try {
                    $keyExists = $tmxHandler->checkCorrectKey( $this->__postInput[ 'tm_key' ] );
                } catch ( Exception $e ) {
                    /* PROVIDED KEY IS NOT VALID OR WRONG, $keyExists IS NOT SET */
                    Log::doJsonLog( $e->getMessage() );
                }

                if ( !isset( $keyExists ) || $keyExists === false ) {
                    $this->result[ 'errors' ][] = [ "code" => -9, "message" => "TM key is not valid." ];
                    Log::doJsonLog( __METHOD__ . " -> TM key is not valid." );
                    $this->result[ 'success' ] = false;
                } else {
                    $this->result[ 'errors' ]  = [];
                    $this->result[ 'success' ] = true;
                }

                break;
            case 'clearNotCompletedUploads':
                try {
                    ConnectedServices\GDrive\Session::cleanupSessionFiles();
                } catch ( Exception $e ) {
                    Log::doJsonLog( "ajaxUtils::clearNotCompletedUploads : " . $e->getMessage() );
                }
                break;

        }

    }
}
