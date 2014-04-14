<?php
include_once INIT::$MODEL_ROOT . "/queries.php";

class outsourceToTranslatedController extends ajaxController {
	
	private $pid;
	private $ppassword;
	private $jobList;

    public function __construct() {

        $this->disableSessions();
        parent::__construct();

        $filterArgs = array(
                'pid'             => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'ppassword'       => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
                'jobs'            => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_REQUIRE_ARRAY  | FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
        );

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );

        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI
        //$this->__postInput = filter_var_array( $_POST, $filterArgs );

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

        if( !empty( $this->result[ 'errors' ] ) ){
            return -1; // ERROR
        }

    }

    public function doAction() {

        $this->result[ 'code' ] = 1;
//		simulation valid for this analysis file: http://matecat.local/analyze/009-INDESIGN-Welcome_to_GDRIVE_mini.inx_en-US_de-DE.sdlxliff/15758-8ba30e8d72c9
        $this->result[ 'data' ] = array (
                                        0 =>
                                                array (
                                                        'id' => '19060-1',
                                                        'price' => 20.12,
                                                        'delivery_date' => '2014-04-01 11:00',
                                                ),
                                        1 =>
                                                array (
                                                        'id' => '19060-2',
                                                        'price' => 30.12,
                                                        'delivery_date' => '2014-04-01 11:05',
                                                ),
                                        2 =>
                                                array (
                                                        'id' => '19059-1',
                                                        'price' => 10.54,
                                                        'delivery_date' => '2014-04-01 11:10',
                                                ),
                                        3 =>
                                                array (
                                                        'id' => '19059-2',
                                                        'price' => 38.15,
                                                        'delivery_date' => '2014-04-01 11:15',
                                                ),
                                        4 =>
                                                array (
                                                        'id' => '19061-1',
                                                        'price' => 50.72,
                                                        'delivery_date' => '2014-04-01 11:20',
                                                ),
                                        5 =>
                                                array (
                                                        'id' => '19061-2',
                                                        'price' => 8.4,
                                                        'delivery_date' => '2014-04-01 11:25',
                                                ),
                                        6 =>
                                                array (
                                                        'id' => '19061-3',
                                                        'price' => 3.42,
                                                        'delivery_date' => '2014-04-01 11:30',
                                                ),
                                );


    }

}

?>
