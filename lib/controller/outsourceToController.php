<?php
include_once INIT::$MODEL_ROOT . "/queries.php";

class outsourceToController extends ajaxController {
	
	private $pid;
	private $ppassword;
	private $jobList;

    public function __construct() {

        //SESSION ENABLED
        parent::__construct();

        $filterArgs = array(
                'pid'             => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'ppassword'       => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
                'jobs'            => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_REQUIRE_ARRAY  | FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
        );

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );

        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI
        //$__postInput = filter_var_array( $_POST, $filterArgs );

        $this->pid       = $__postInput[ 'pid' ];
        $this->ppassword = $__postInput[ 'ppassword' ];
        $this->jobList   = $__postInput[ 'jobs' ];

        if( empty( $this->pid ) ){
            $this->result[ 'errors' ][] = array( "code" => -1, "message" => "No id project provided" );
        }

        if( empty( $this->ppassword ) ){
            $this->result[ 'errors' ][] = array( "code" => -2, "message" => "No project Password Provided" );
        }

        if( empty( $this->jobList ) ){
            $this->result[ 'errors' ][] = array( "code" => -3, "message" => "No job list Provided" );
        }

//        Log::doLog(  $this->jobList  );

    }

    public function doAction() {

        if( !empty( $this->result[ 'errors' ] ) ){
            return -1; // ERROR
        }

        $outsourceTo = new OutsourceTo_Translated();
        $outsourceTo->setPid( $this->pid );
        $outsourceTo->setPpassword( $this->ppassword );
        $outsourceTo->setJobList( $this->jobList );
        $outsourceTo->performQuote();

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
//        Log::doLog( $client_output );

        $this->result[ 'code' ]       = 1;
        $this->result[ 'data' ]       = array_values( $client_output );
        $this->result[ 'return_url' ] = array(
                'url_ok' => $outsourceTo->getOutsourceLoginUrlOk(),
                'url_ko' => $outsourceTo->getOutsourceLoginUrlKo(),
        );

    }

}
