<?php
include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . '/AjaxPasswordCheck.php';

class setCurrentSegmentController extends ajaxcontroller {

	private $id_segment;
	private $id_job;


    public function __construct() {
        parent::__construct();

        $filterArgs = array(
            'id_segment' => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'id_job'     => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'password'   => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
        );

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );

        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI Test scripts
        //$__postInput = filter_var_array( $_POST, $filterArgs );

        $this->id_segment = (int)$__postInput[ 'id_segment' ];
        $this->id_job     = (int)$__postInput[ 'id_job' ];
        $this->password   = $__postInput[ 'password' ];

    }

    public function doAction() {

        //get Job Infos
        $job_data = getJobData( (int) $this->id_job );

        $pCheck = new AjaxPasswordCheck();
        //check for Password correctness
        if( !$pCheck->grantJobAccessByJobData( $job_data, $this->password ) ){
            $this->result['error'][] = array( "code" => -10, "message" => "wrong password" );
        }

        if ( empty( $this->id_segment ) ) {
            $this->result[ 'error' ][ ] = array( "code" => -1, "message" => "missing segment id" );
        }

        if ( empty( $this->id_job ) ) {
            $this->result[ 'error' ][ ] = array( "code" => -2, "message" => "missing Job id" );
        }

        if ( !empty( $this->result[ 'error' ] ) ) {
            //no action on errors
            return;
        }

        $insertRes     = setCurrentSegmentInsert( $this->id_segment, $this->id_job, $this->password );
        $nextSegmentId = getNextUntranslatedSegment( $this->id_segment, $this->id_job, $this->password );

        $this->result[ 'code' ] = 1;
        $this->result[ 'data' ] = "OK";

        $nSegment = array( 'id' => null );
        if( isset( $nextSegmentId[ 0 ][ 'id' ] ) ){
            //if there are results check for next id,
            //otherwise get the first one in the list
            $nSegment = $nextSegmentId[0];
            foreach( $nextSegmentId as $seg ){
                if( $seg['id'] > $this->id_segment ){
                    $nSegment = $seg;
                    break;
                }
            }
        }

        $this->result[ 'nextSegmentId' ] = $nSegment[ 'id' ];

    }

}