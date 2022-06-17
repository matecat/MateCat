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
     * ------------------------------------------
     * Note 2021-01-12
     * ------------------------------------------
     *
     * This field refers to the current job password
     * which is actually needed by isRevision() function
     * In near future we should remote it
     *
     * @var string|null
     */
    protected $received_password;

    protected $api_output = [];

    /**
     * Carry the result from Executed Controller Action and returned in json format to the Client
     *
     * @var array
     */
    protected $result = [ "errors" => [], "data" => [] ];
    protected $id_segment;

    protected $split_num = null;

    /**
     * Class constructor, initialize the header content type.
     */
    protected function __construct() {

        $this->startTimer();

        $buffer = ob_get_contents();
        ob_get_clean();
        // ob_start("ob_gzhandler");        // compress page before sending //Not supported for json response on ajax calls
        header( 'Content-Type: application/json; charset=utf-8' );


        if ( !Bootstrap::areMandatoryKeysPresent() ) {
            $output           = INIT::$CONFIG_VERSION_ERR_MESSAGE;
            $this->result     = [ "errors" => [ [ "code" => -1000, "message" => $output ] ], "data" => [] ];
            $this->api_output = [ "errors" => [ [ "code" => -1000, "message" => $output ] ], "data" => [] ];
            Log::doJsonLog( "Error: " . INIT::$CONFIG_VERSION_ERR_MESSAGE );
            $this->finalize();
            exit;
        }

        $this->featureSet = new FeatureSet();

        $filterArgs = array(
                'current_password' => array(
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
        );

        $__postInput   = (object)filter_input_array( INPUT_POST, $filterArgs );

        if( isset( $__postInput->current_password) ){
            $this->received_password = $__postInput->current_password;
        }
    }

    /**
     * Call the output in JSON format
     *
     */
    public function finalize() {
        $toJson = json_encode( $this->result );

        if (!ob_get_status())  {
            ob_start();
        }

        echo $toJson;

        $this->logPageCall();

    }

    /**
     * @return bool
     */
    public static function isRevision() {

        $controller = static::getInstance();

        if (isset($controller->id_job) and isset($controller->received_password)){
            $jid        = $controller->id_job;
            $password   = $controller->received_password;
            $isRevision = CatUtils::getIsRevisionFromIdJobAndPassword( $jid, $password );

            if ( null === $isRevision ) {
                $isRevision = CatUtils::getIsRevisionFromReferer();
            }

            return $isRevision;
        }

        return CatUtils::getIsRevisionFromReferer();
    }

    public function parseIDSegment() {
        @list( $this->id_segment, $this->split_num ) = explode( "-", $this->id_segment );
    }
}
