<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 19/01/15
 * Time: 16.50
 */

class setRevisionController extends ajaxController {

    private $id_job;
    private $password_job;
    private $err_typing;
    private $err_translation;
    private $err_terminology;
    private $err_language;
    private $err_style;
    private $original_translation;
    private $reviseClass;

    private static $accepted_values = array(
            Constants_Revise::CLIENT_VALUE_NONE,
            Constants_Revise::CLIENT_VALUE_MINOR,
            Constants_Revise::CLIENT_VALUE_MAJOR
    );

    public function __construct() {

        parent::__construct();

        $filterArgs = array(
                'job'             => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'segment'         => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'jpassword'       => array(
                        'filter'  => FILTER_SANITIZE_STRING,
                        'flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW
                ),
                'err_typing'      => array(
                        'filter'  => FILTER_CALLBACK,
                        'options' => array( "setRevisionController", "sanitizeFieldValue" )
                ),
                'err_translation' => array(
                        'filter'  => FILTER_CALLBACK,
                        'options' => array( "setRevisionController", "sanitizeFieldValue" )
                ),
                'err_terminology' => array(
                        'filter'  => FILTER_CALLBACK,
                        'options' => array( "setRevisionController", "sanitizeFieldValue" )
                ),
                'err_language'     => array(
                        'filter'  => FILTER_CALLBACK,
                        'options' => array( "setRevisionController", "sanitizeFieldValue" )
                ),
                'err_style'       => array(
                        'filter'  => FILTER_CALLBACK,
                        'options' => array( "setRevisionController", "sanitizeFieldValue" )
                ),
                'original'        => array(
                        'filter'  => FILTER_UNSAFE_RAW
                )
        );

        $postInput                  = filter_input_array( INPUT_POST, $filterArgs );
        $this->id_job               = $postInput[ 'job' ];
        $this->password_job         = $postInput[ 'jpassword' ];
        $this->id_segment           = $postInput[ 'segment' ];
        $this->err_typing           = $postInput[ 'err_typing' ];
        $this->err_translation      = $postInput[ 'err_translation' ];
        $this->err_terminology      = $postInput[ 'err_terminology' ];
        $this->err_language         = $postInput[ 'err_language' ];
        $this->err_style            = $postInput[ 'err_style' ];

        list( $this->original_translation, $none ) = CatUtils::parseSegmentSplit( CatUtils::view2rawxliff( $postInput[ 'original' ] ), ' ' );

        Log::doLog($_POST);

        if ( empty( $this->id_job ) ) {
            $this->result[ 'errors' ][ ] = array( 'code' => -1, 'message' => 'Job ID missing' );
        }

        if ( empty( $this->id_segment ) ) {
            $this->result[ 'errors' ][ ] = array( 'code' => -2, 'message' => 'Segment ID missing' );
        }

        if ( empty( $this->password_job ) ) {
            $this->result[ 'errors' ][ ] = array( 'code' => -3, 'message' => 'Job password missing' );
        }

    }

    /**
     * When Called it perform the controller action to retrieve/manipulate data
     *
     * @throws Exception
     */
    public function doAction() {
        if ( !empty( $this->result[ 'errors' ] ) ) {
            return;
        }

        $job_data = getJobData( (int)$this->id_job, $this->password_job );
        if ( empty( $job_data ) ) {
            $msg = "Error : empty job data \n\n " . var_export( $_POST, true ) . "\n";
            Log::doLog( $msg );
            Utils::sendErrMailReport( $msg );
        }

        //add check for job status archived.
        if ( strtolower( $job_data[ 'status' ] ) == Constants_JobStatus::STATUS_ARCHIVED ) {
            $this->result[ 'errors' ][ ] = array( "code" => -6, "message" => "job archived" );
        }

        $this->parseIDSegment();
        $pCheck = new AjaxPasswordCheck();
        //check for Password correctness
        if ( empty( $job_data ) || !$pCheck->grantJobAccessByJobData( $job_data, $this->password_job, $this->id_segment ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -7, "message" => "wrong password" );
        }

        $wStruct = new WordCount_Struct();

        $wStruct->setIdJob( $this->id_job );
        $wStruct->setJobPassword( $this->password_job );
        $wStruct->setNewWords( $job_data[ 'new_words' ] );
        $wStruct->setDraftWords( $job_data[ 'draft_words' ] );
        $wStruct->setTranslatedWords( $job_data[ 'translated_words' ] );
        $wStruct->setApprovedWords( $job_data[ 'approved_words' ] );
        $wStruct->setRejectedWords( $job_data[ 'rejected_words' ] );


        $reviseDAO = new Revise_ReviseDAO( Database::obtain() );

        //store segment revision in DB
        $revisionStruct             = Revise_ReviseStruct::getStruct();
        $revisionStruct->id_job     = $this->id_job;
        $revisionStruct->id_segment = $this->id_segment;

        //check if an old revision exists. If it does, retrieve it and save it.
        $oldRevision = $reviseDAO->read( $revisionStruct );
        $oldRevision = ( isset( $oldRevision[ 0 ] ) ) ? $oldRevision[ 0 ] : Revise_ReviseStruct::setDefaultValues( Revise_ReviseStruct::getStruct() );

        $revisionStruct->err_typing           = $this->err_typing;
        $revisionStruct->err_translation      = $this->err_translation;
        $revisionStruct->err_terminology      = $this->err_terminology;
        $revisionStruct->err_language         = $this->err_language;
        $revisionStruct->err_style            = $this->err_style;
        $revisionStruct->original_translation = $this->original_translation;

        //save the new revision in the database.
        try {
            $reviseDAO->create( $revisionStruct );
        } catch ( Exception $e ) {
            Log::doLog( __METHOD__ . " -> " . $e->getMessage() );
            $this->result[ 'errors' ] [ ] = array( 'code' => -4, 'message' => "Insert failed" );

            return;
        }

        /**
         * Refresh error counters in the job table
         */

        $errorCountStruct = new ErrorCount_DiffStruct( $oldRevision, $revisionStruct );
        $errorCountStruct->setIdJob( $this->id_job );
        $errorCountStruct->setJobPassword( $this->password_job );

        $errorCountDao = new ErrorCount_ErrorCountDAO( Database::obtain() );
        try {

            $this->reviseClass = new Constants_Revise;

            $jobQA = new Revise_JobQA(
                $this->id_job,
                $this->password_job,
                $wStruct->getTotal(),
                $this->reviseClass
            );

            list($jobQA, $this->reviseClass) = $this->featureSet->filter("overrideReviseJobQA", [$jobQA, $this->reviseClass], $this->id_job,
                    $this->password_job,
                    $wStruct->getTotal());


            if( $errorCountStruct->thereAreDifferences() ){
                $errorCountDao->update( $errorCountStruct );
                $jobQA->cleanErrorCache();
            }

        } catch ( Exception $e ) {
            Log::doLog( __METHOD__ . " -> " . $e->getMessage() );
            $this->result[ 'errors' ] [ ] = array( 'code' => -5, 'message' => "Did not update job error counters." );

            return;
        }

        /**
         * Retrieve information about job errors
         * ( Note: these information are fed by the revision process )
         * @see setRevisionController
         */
        $jobQA->retrieveJobErrorTotals();
        $jobVote = $jobQA->evalJobVote();

        $this->result[ 'data' ][ 'message' ]               = 'OK';
        $this->result[ 'data' ][ 'stat_quality' ]          = $jobQA->getQaData();
        $this->result[ 'data' ][ 'overall_quality' ]       = $jobVote[ 'minText' ];
        $this->result[ 'data' ][ 'overall_quality_class' ] = strtolower( str_replace( ' ', '', $jobVote[ 'minText' ] ) );

    }

    /**
     * @param $fieldVal string
     *
     * @return string The sanitized field
     */
    private static function sanitizeFieldValue( $fieldVal ) {
        //if $fieldVal is not one of the accepted values, force it to "none"
        if ( !in_array( $fieldVal, self::$accepted_values ) ) {
            return Constants_Revise::NONE;
        }

        return Constants_Revise::$ERR_TYPES_MAP[ $fieldVal ];
    }

}