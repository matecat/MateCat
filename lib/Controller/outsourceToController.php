<?php
/**
 * Controller to receive ajax request to make an External provider Quote
 */

/**
 * Class outsourceToController
 */
class outsourceToController extends ajaxController {

    /**
     * The project ID
     * @var int
     */
    private $pid;

    /**
     * The project Password
     * @var string
     */
    private $ppassword;

    /**
     * The currency the customer is viewing the outsource price in
     * @var string
     */
    private $currency;

    /**
     * The timezone the customer is viewing the outsource delivery date in
     * @var string
     */
    private $timezone;

    /**
     * The delivery date the customer has chosen with 'need it faster' feature
     * @var string (representing a date)
     */
    private $fixedDelivery;

    /**
     * The type of service the customer has chosen
     * @var string (can be only "premium" or "professional")
     */
    private $typeOfService;

    /**
     * A list of job_id/job_password for quote request
     *
     * <pre>
     * Ex:
     *   array(
     *      0 => array(
     *          'id' => 5901,
     *          'jpassword' => '6decb661a182',
     *      ),
     *   );
     * </pre>
     *
     * @var array
     */
    private $jobList;

    /**
     * Class constructor, validate/sanitize incoming params
     *
     */
    public function __construct() {

        //SESSION ENABLED
        parent::__construct();

        $filterArgs = array(
                'pid'             => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'ppassword'       => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
                'currency'        => array( 'filter' => FILTER_SANITIZE_STRING ),
                'timezone'        => array( 'filter' => FILTER_SANITIZE_STRING ),
                'fixedDelivery'   => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'typeOfService'   => array( 'filter' => FILTER_SANITIZE_STRING ),
                'jobs'            => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_REQUIRE_ARRAY  | FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
        );

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );

        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI
        //$__postInput = filter_var_array( $_POST, $filterArgs );

        $this->pid       = $__postInput[ 'pid' ];
        $this->ppassword = $__postInput[ 'ppassword' ];
        $this->currency  = $__postInput[ 'currency' ];
        $this->timezone  = $__postInput[ 'timezone' ];
        $this->fixedDelivery = $__postInput[ 'fixedDelivery' ];
        $this->typeOfService = $__postInput[ 'typeOfService' ];
        $this->jobList   = $__postInput[ 'jobs' ];

        if( empty( $this->pid ) ){
            $this->result[ 'errors' ][] = array( "code" => -1, "message" => "No id project provided" );
        }

        if( empty( $this->ppassword ) ){
            $this->result[ 'errors' ][] = array( "code" => -2, "message" => "No project Password Provided" );
        }

        if ( empty( $this->currency ) ) {
            $this->currency = @$_COOKIE[ "matecat_currency" ];
        }

        if ( empty( $this->timezone ) && $this->timezone !== "0" ) {
            $this->timezone = @$_COOKIE[ "matecat_timezone" ];
        }

        if ( !in_array( $this->typeOfService, array( "premium" , "professional") ) ) {
            $this->typeOfService = "professional";
        }

        //        Log::doJsonLog(  $this->jobList  );
        /**
         * The Job List form
         *
         * <pre>
         * Ex:
         *   array(
         *      0 => array(
         *          'id' => 5901,
         *          'jpassword' => '6decb661a182',
         *      ),
         *   );
         * </pre>
         */
        if( empty( $this->jobList ) ){
            $this->result[ 'errors' ][] = array( "code" => -3, "message" => "No job list Provided" );
        }

    }

    /**
     * Perform Controller Action
     *
     * @return int|null
     */
    public function doAction() {

        if( !empty( $this->result[ 'errors' ] ) ){
            return -1; // ERROR
        }

        $outsourceTo = new OutsourceTo_Translated();
        $outsourceTo->setPid( $this->pid )
                    ->setPpassword( $this->ppassword )
                    ->setCurrency( $this->currency )
                    ->setTimezone( $this->timezone )
                    ->setJobList( $this->jobList )
                    ->setFixedDelivery( $this->fixedDelivery )
                    ->setTypeOfService( $this->typeOfService )
                    ->performQuote();

        /*
         * Example:
         *
         *   $client_output = array (
         *       '5901-6decb661a182' =>
         *               array (
         *                       'id' => '5901-6decb661a182',
         *                       'quantity' => '1',
         *                       'name' => 'MATECAT_5901-6decb661a182',
         *                       'quote_pid' => '11180933',
         *                       'source' => 'it-IT',
         *                       'target' => 'en-GB',
         *                       'price' => '12.00',
         *                       'words' => '120',
         *                       'show_info' => '0',
         *                       'delivery_date' => '2014-04-29T15:00:00Z',
         *               ),
         *   );
         */
        $client_output = $outsourceTo->getQuotesResult();
//        Log::doJsonLog( $client_output );

        $this->result[ 'code' ]       = 1;
        $this->result[ 'data' ]       = array_values( $client_output );
        $this->result[ 'return_url' ] = array(
                'url_ok'          => $outsourceTo->getOutsourceLoginUrlOk(),
                'url_ko'          => $outsourceTo->getOutsourceLoginUrlKo(),
                'confirm_urls'    => $outsourceTo->getOutsourceConfirm(),
        );

    }

}
