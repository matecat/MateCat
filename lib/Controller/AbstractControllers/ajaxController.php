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
    protected $result = array("errors" => array(), "data" => array());

    protected $id_segment;
    protected $split_num = null;

    /**
     * Class constructor, initialize the header content type.
     */
    protected function __construct() {

        $buffer = ob_get_contents();
        ob_get_clean();
        // ob_start("ob_gzhandler");        // compress page before sending //Not supported for json response on ajax calls
        header('Content-Type: application/json; charset=utf-8');


	    if( !Bootstrap::areMandatoryKeysPresent() ) {
			$output = INIT::$CONFIG_VERSION_ERR_MESSAGE;
			$this->result     = array("errors" => array( array( "code" => -1000, "message" => $output ) ), "data" => array() );
			$this->api_output = array("errors" => array( array( "code" => -1000, "message" => $output ) ), "data" => array() );
            \Log::doLog("Error: " . INIT::$CONFIG_VERSION_ERR_MESSAGE);
			$this->finalize();
			exit;
		}

		$this->featureSet = new FeatureSet();

    }

    /**
     * Call the output in JSON format
     *
     */
    public function finalize() {
        $toJson = json_encode( $this->result );

        //Log Errors
        Utils::raiseJsonExceptionError( false );

        echo $toJson;
    }

    public static function isRevision(){
        $_from_url = parse_url( @$_SERVER['HTTP_REFERER'] );
        $is_revision_url = strpos( $_from_url['path'] , "/revise" ) === 0;
        return $is_revision_url;
    }

    public function parseIDSegment(){
        @list( $this->id_segment, $this->split_num ) = explode( "-", $this->id_segment );
    }

}
