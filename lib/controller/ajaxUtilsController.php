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

                //validate the key
                //This piece of code need to be executed every time
                $keyExists = $apiKeyService->checkCorrectKey( $this->__postInput['tm_key'] );
                if ( !$keyExists ) {
                    $this->result[ 'errors' ][ ] = array( "code" => -9, "message" => "TM key is not valid." );
                    Log::doLog( __METHOD__ . " -> TM key is not valid." );
                    $this->result[ 'success' ] = false;
                } else {
                    $this->result[ 'errors' ][ ] = new stdClass();
                    $this->result[ 'success' ] = true;
                }
                break;
        }

    }
}