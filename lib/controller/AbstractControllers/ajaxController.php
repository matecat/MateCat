<?php
/**
 * Created by PhpStorm.
 * Date: 27/01/14
 * Time: 18.57
 * 
 */

/**
 * Class ajaxController
 * Abstract class to manage the Ajax requests
 *
 */
abstract class ajaxController extends controller {

    /**
     * Carry the result from Executed Controller Action and returned in json format to the Client
     *
     * @var array
     */
    protected $result = array("error" => array(), "data" => array());

    protected $uid;
    protected $userIsLogged = false;
    protected $userMail;

    /**
     * Class constructor, initialize the header content type.
     */
    protected function __construct() {
        parent::__construct();

        $buffer = ob_get_contents();
        ob_get_clean();
        // ob_start("ob_gzhandler");        // compress page before sending //Not supported for json response on ajax calls
        header('Content-Type: application/json; charset=utf-8');


	    if( !parent::isRightVersion() ) {
			$output = INIT::$CONFIG_VERSION_ERR_MESSAGE;
			$this->result     = array("errors" => array( array( "code" => -1000, "message" => $output ) ), "data" => array() );
			$this->api_output = array("errors" => array( array( "code" => -1000, "message" => $output ) ), "data" => array() );
			$this->finalize();
			exit;
		}
    }

    /**
     * Call the output in JSON format
     *
     */
    public function finalize() {
        $toJson = json_encode( $this->result );

        if ( function_exists( "json_last_error" ) ) {
            switch ( json_last_error() ) {
                case JSON_ERROR_NONE:
//              	  Log::doLog(' - No errors');
                    break;
                case JSON_ERROR_DEPTH:
                    Log::doLog( ' - Maximum stack depth exceeded' );
                    break;
                case JSON_ERROR_STATE_MISMATCH:
                    Log::doLog( ' - Underflow or the modes mismatch' );
                    break;
                case JSON_ERROR_CTRL_CHAR:
                    Log::doLog( ' - Unexpected control character found' );
                    break;
                case JSON_ERROR_SYNTAX:
                    Log::doLog( ' - Syntax error, malformed JSON' );
                    break;
                case JSON_ERROR_UTF8:
                    Log::doLog( ' - Malformed UTF-8 characters, possibly incorrectly encoded' );
                    break;
                default:
                    Log::doLog( ' - Unknown error' );
                    break;
            }
        }

        echo $toJson;
    }

    public function checkLogin() {
        //Warning, sessions enabled, disable them after check, $_SESSION is in read only mode after disable
        parent::sessionStart();
        $this->userIsLogged = ( isset( $_SESSION[ 'cid' ] ) && !empty( $_SESSION[ 'cid' ] ) );
        $this->userMail     = ( isset( $_SESSION[ 'cid' ] ) && !empty( $_SESSION[ 'cid' ] ) ? $_SESSION[ 'cid' ] : null );
        $this->uid          = ( isset( $_SESSION[ 'uid' ] ) && !empty( $_SESSION[ 'uid' ] ) ? $_SESSION[ 'uid' ] : null );
        parent::disableSessions();

        return $this->userIsLogged;
    }

}
