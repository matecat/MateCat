<?php

class changeJobsStatusController extends ajaxController {

    private $res_type;
    private $res_id;
    private $new_status = Constants_JobStatus::STATUS_ACTIVE;
    private $password = "fake wrong password";

    public function __construct() {

        //SESSION START
        parent::__construct();
        parent::readLoginInfo();

        $filterArgs = array(
                'res'           => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'id'            => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'password'      => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'new_status'    => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'pn'            => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],

        );

        $postInput = filter_input_array( INPUT_POST, $filterArgs );

        ( !empty( $postInput[ 'password' ] ) ? $this->password = $postInput[ 'password' ] : null );

        $this->res_type   = $postInput[ 'res' ];
        $this->res_id     = $postInput[ 'id' ];

        if ( Constants_JobStatus::isAllowedStatus( $postInput[ 'new_status' ] ) ) {
            $this->new_status = $postInput[ 'new_status' ];
        } else {
            throw new Exception( "Invalid Status" );
        }

    }

   public function doAction() {

        if ( ! $this->userIsLogged ) {
            throw new Exception( "User Not Logged." );
        }

        if ( $this->res_type == "prj" ) {

            try {
                $project = Projects_ProjectDao::findByIdAndPassword( $this->res_id, $this->password );
            } catch( Exception $e ){
                $msg = "Error : wrong password provided for Change Project Status \n\n " . var_export( $_POST, true ) . "\n";
                Log::doJsonLog( $msg );
                Utils::sendErrMailReport( $msg );
                return null;
            }

            $chunks = $project->getJobs();

            Jobs_JobDao::updateAllJobsStatusesByProjectId( $project->id, $this->new_status );

            foreach( $chunks as $chunk ){
                $lastSegmentsList = Translations_SegmentTranslationDao::getMaxSegmentIdsFromJob( $chunk );
                Translations_SegmentTranslationDao::updateLastTranslationDateByIdList( $lastSegmentsList, Utils::mysqlTimestamp( time() ) );
            }

        } else {

            try {
                $firstChunk = Chunks_ChunkDao::getByIdAndPassword( $this->res_id, $this->password );
            } catch( Exception $e ){
                $msg = "Error : wrong password provided for Change Job Status \n\n " . var_export( $_POST, true ) . "\n";
                Log::doJsonLog( $msg );
                Utils::sendErrMailReport( $msg );
                return null;
            }

            Jobs_JobDao::updateJobStatus( $firstChunk, $this->new_status );
            $lastSegmentsList = Translations_SegmentTranslationDao::getMaxSegmentIdsFromJob( $firstChunk );
            Translations_SegmentTranslationDao::updateLastTranslationDateByIdList( $lastSegmentsList, Utils::mysqlTimestamp( time() ) );

        }

       $this->result[ 'code' ]    = 1;
       $this->result[ 'data' ]    = "OK";
       $this->result[ 'status' ]  = $this->new_status;

    }

}
