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
            \Log::doJsonLog( "Error: " . INIT::$CONFIG_VERSION_ERR_MESSAGE );
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
        echo $toJson;

        $this->logPageCall();

    }

    /**
     * @return bool
     */
    public static function isRevision() {
        $_from_url = parse_url( @$_SERVER['HTTP_REFERER'] );
        $is_revision_url = strpos( $_from_url['path'] , "/revise" ) === 0;
        return $is_revision_url;
    }

    /**
     * TODO: move this method to a Utils static class
     *
     * @param FeatureSet $featureSet
     *
     * @return mixed
     */
    public static function getRefererSourcePageCode( FeatureSet $featureSet ) {
        if ( !static::isRevision() ) {
            $sourcePage = Constants::SOURCE_PAGE_TRANSLATE ;
        }
        else {
            $sourcePage = Constants::SOURCE_PAGE_REVISION ;
        }

        return $featureSet->filter('filterSourcePage', $sourcePage ) ;
    }

    public function parseIDSegment() {
        @list( $this->id_segment, $this->split_num ) = explode( "-", $this->id_segment );
    }

}
