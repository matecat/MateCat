<?php
include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . '/AjaxPasswordCheck.php';

class setCurrentSegmentController extends ajaxcontroller {

	private $id_segment;
	private $id_job;


    public function __construct() {
        parent::__construct();

        $this->id_segment = (int)$this->get_from_get_post( 'id_segment' );
        $this->id_job     = (int)$this->get_from_get_post( 'id_job' );
        $this->password   = $this->get_from_get_post("password");

    }

    public function doAction() {

        //get Job Infos
        $job_data = getJobData( (int) $this->id_job );

        $pCheck = new AjaxPasswordCheck();
        //check for Password correctness
        if( !$pCheck->grantJobAccessByJobData( $job_data, $this->password ) ){
            $this->result['error'][] = array( "code" => -3, "message" => "wrong password" );
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

        $insertRes     = setCurrentSegmentInsert( $this->id_segment, $this->id_job );
        $nextSegmentId = getNextUntranslatedSegment( $this->id_segment, $this->id_job );

        $this->result[ 'code' ] = 1;
        $this->result[ 'data' ] = "OK";

        $this->result[ 'nextSegmentId' ] = isset( $nextSegmentId[ 0 ][ 'id' ] ) ? $nextSegmentId[ 0 ][ 'id' ] : '';

    }

}