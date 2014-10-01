<?php
/**
 * Created by JetBrains PhpStorm.
 * User: domenico
 * Date: 31/07/13
 * Time: 18.54
 * To change this template use File | Settings | File Templates.
 */

class ajaxUtilsController extends ajaxController {

    private $__postInput = null;
    private $__getInput = null;

    public function __construct() {

        //SESSION ENABLED
        parent::sessionStart();
        parent::__construct();

//        $gets = $_GET;
//        foreach ( $gets as $key => &$value ) {
//            $value = filter_var( $value, FILTER_SANITIZE_STRING, array( 'flags' => FILTER_FLAG_STRIP_LOW ) );
//        }
//        $this->__getInput = $gets;

        $posts = $_POST;
        foreach ( $posts as $key => &$value ) {
            $value = filter_var( $value, FILTER_SANITIZE_STRING, array( 'flags' => FILTER_FLAG_STRIP_LOW ) );
        }

        $this->__postInput = $posts;

    }

    public function doAction() {

        switch ( $this->__postInput['exec'] ) {
            case 'stayAnonymous':
                unset( $_SESSION[ '_anonym_pid' ] );
                unset( $_SESSION[ 'incomingUrl' ] );
                unset( $_SESSION[ '_newProject' ] );
                break;
			case 'ping':
                $db = Database::obtain();
                $db->query("SELECT 1");
				$this->result['data'] = array( "OK", time() ); 
				break;
            case 'checkTMKey':
                //get MyMemory apiKey service
                $apiKeyService = TMSServiceFactory::getAPIKeyService();

                Log::doLog( $this->__postInput['tm_key'] );

                //validate the key
                try {
                    $keyExists = $apiKeyService->checkCorrectKey( $this->__postInput['tm_key'] );
                } catch ( Exception $e ){
                    /* PROVIDED KEY IS NOT VALID OR WRONG, $keyExists IS NOT SET */
                    Log::doLog( $e->getMessage() );
                }

                if ( !isset($keyExists) || $keyExists === false ) {
                    $this->result[ 'errors' ][ ] = array( "code" => -9, "message" => "TM key is not valid." );
                    Log::doLog( __METHOD__ . " -> TM key is not valid." );
                    $this->result[ 'success' ] = false;
                } else {
                    $this->result[ 'errors' ] = array();
                    $this->result[ 'success' ] = true;
                }
                break;

            case 'tmxUploadStatus':

                $tmxHandler = TMSServiceFactory::getTMXService( 1 /* MyMemory */ );
                $allMemories = $tmxHandler->getStatus( $this->__postInput['tm_key'], $this->__postInput['tmx_name'] );
                $this->result[ 'errors' ] = array();
                Log::doLog($allMemories);
                if ( "200" != $allMemories[ 'responseStatus' ] || 0 == count( $allMemories[ 'responseData' ][ 'tm' ] ) ) {
                    //what the hell? No memories although I've just loaded some? Eject!
                    $this->result[ 'errors' ][ ] = array(
                            "code" => -15, "message" => "Cant't load TMX files right now, try later"
                    );

                    return false;
                }

                $tmx_max_id = 0;
                $current_tm = array();

                //scan through memories
                foreach ( $allMemories[ 'responseData' ][ 'tm' ] as $memory ) {
                    //obtain max id
                    $tmx_max_id = max( $tmx_max_id, $memory[ 'id' ] );

                    //if maximum is current, pick it (it means that, among duplicates, it's the latest)
                    if ( $tmx_max_id == $memory[ 'id' ] ) {
                        $current_tm = $memory;
                    }
                }

                switch ( $current_tm[ 'status' ] ) {
                    case "0":
                        //wait for the daemon to process it
                        //LOADING
                        Log::doLog( "waiting for \"" . $current_tm[ 'file_name' ] . "\" to be loaded into MyMemory" );
                        $this->result[ 'data' ]      = array(
                                "done"  => $current_tm[ "temp_seg_ins" ],
                                "total" => $current_tm[ "num_seg_tot" ],
                        );
                        $this->result[ 'completed' ] = false;
                        break;
                    case "1":
                        //loaded (or error, in any case go ahead)
                        Log::doLog( "\"" . $current_tm[ 'file_name' ] . "\" has been loaded into MyMemory" );
                        $this->result[ 'data' ]      = array(
                                "done"  => $current_tm[ "temp_seg_ins" ],
                                "total" => $current_tm[ "num_seg_tot" ]
                        );
                        $this->result[ 'completed' ] = true;
                        break;
                    default:
                        $this->result[ 'errors' ][ ] = array(
                                "code" => -14, "message" => "Invalid TMX (\"" . $current_tm['file_name'] . "\")"
                        );
                        break;
                }

        }

    }
}