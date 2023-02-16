<?php

use Features\ReviewExtended\ReviewUtils;
use Features\ReviewExtended\Model\QualityReportDao;
use Matecat\SubFiltering\MateCatFilter;

class setRevisionController extends ajaxController {

    private $_postInput;
    private $id_job;
    private $password_job;
    private $err_typing;
    private $err_translation;
    private $err_terminology;
    private $err_language;
    private $err_style;
    private $reviseClass;
    private $revisionNumber;

    private static $accepted_values = [
            Constants_Revise::CLIENT_VALUE_NONE,
            Constants_Revise::CLIENT_VALUE_MINOR,
            Constants_Revise::CLIENT_VALUE_MAJOR
    ];

    public function __construct() {

        parent::__construct();

        $filterArgs = [
                'job'             => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'segment'         => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'jpassword'       => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW
                ],
                'err_typing'      => [
                        'filter'  => FILTER_CALLBACK,
                        'options' => [ "setRevisionController", "sanitizeFieldValue" ]
                ],
                'err_translation' => [
                        'filter'  => FILTER_CALLBACK,
                        'options' => [ "setRevisionController", "sanitizeFieldValue" ]
                ],
                'err_terminology' => [
                        'filter'  => FILTER_CALLBACK,
                        'options' => [ "setRevisionController", "sanitizeFieldValue" ]
                ],
                'err_language'    => [
                        'filter'  => FILTER_CALLBACK,
                        'options' => [ "setRevisionController", "sanitizeFieldValue" ]
                ],
                'err_style'       => [
                        'filter'  => FILTER_CALLBACK,
                        'options' => [ "setRevisionController", "sanitizeFieldValue" ]
                ],
                'original'        => [
                        'filter' => FILTER_UNSAFE_RAW
                ],
                'revision_number' => [
                        'filter' => FILTER_SANITIZE_NUMBER_INT
                ],
        ];

        $this->_postInput      = filter_input_array( INPUT_POST, $filterArgs );
        $this->id_job          = $this->_postInput[ 'job' ];
        $this->password_job    = $this->_postInput[ 'jpassword' ];
        $this->id_segment      = $this->_postInput[ 'segment' ];
        $this->err_typing      = $this->_postInput[ 'err_typing' ];
        $this->err_translation = $this->_postInput[ 'err_translation' ];
        $this->err_terminology = $this->_postInput[ 'err_terminology' ];
        $this->err_language    = $this->_postInput[ 'err_language' ];
        $this->err_style       = $this->_postInput[ 'err_style' ];
        $this->revisionNumber  = $this->_postInput[ 'revision_number' ];

        if ( empty( $this->id_job ) ) {
            $this->result[ 'errors' ][] = [ 'code' => -1, 'message' => 'Job ID missing' ];
        }

        if ( empty( $this->id_segment ) ) {
            $this->result[ 'errors' ][] = [ 'code' => -2, 'message' => 'Segment ID missing' ];
        }

        if ( empty( $this->password_job ) ) {
            $this->result[ 'errors' ][] = [ 'code' => -3, 'message' => 'Job password missing' ];
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

        $featureSet = $this->getFeatureSet();

        $job_data = Chunks_ChunkDao::getByIdAndPassword( $this->id_job, $this->password_job );
        $project  = $job_data->getProject();
        $featureSet->loadForProject( $project );

        /** @var MateCatFilter $Filter */
        $Filter = MateCatFilter::getInstance( $featureSet, $job_data->source, $job_data->target, [] );

        list( $original_translation, $none ) = CatUtils::parseSegmentSplit( $this->_postInput[ 'original' ], '', $Filter );

        //add check for job status archived.
        if ( strtolower( $job_data[ 'status' ] ) == Constants_JobStatus::STATUS_ARCHIVED ) {
            $this->result[ 'errors' ][] = [ "code" => -6, "message" => "job archived" ];
        }

        $this->parseIDSegment();

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
        $revisionStruct->original_translation = $original_translation;

        //save the new revision in the database.
        try {
            $reviseDAO->create( $revisionStruct );
        } catch ( Exception $e ) {
            Log::doJsonLog( __METHOD__ . " -> " . $e->getMessage() );
            $this->result[ 'errors' ] [] = [ 'code' => -4, 'message' => "Insert failed" ];

            return;
        }

        /**
         * Refresh error counters in the job table
         */

        $chunkReview = CatUtils::getQualityInfoOrChunkReviewStructFromJobStruct( $job_data, $this->featureSet );

        if ( $this->featureSet->hasRevisionFeature() ) {
            $reviseIssues     = [];
            $qualityReportDao = new QualityReportDao();

            $qa_data = $qualityReportDao->getReviseIssuesByChunk(
                    $job_data->id, $job_data->password,
                    ReviewUtils::revisionNumberToSourcePage($this->revisionNumber)
            );

            foreach ( $qa_data as $issue ) {
                if ( !isset( $reviseIssues[ $issue->id_category ] ) ) {
                    $reviseIssues[ $issue->id_category ] = [
                            'name'   => $issue->issue_category_label,
                            'founds' => [
                                    $issue->issue_severity => 1
                            ]
                    ];
                } else {
                    if ( !isset( $reviseIssues[ $issue->id_category ][ 'founds' ][ $issue->issue_severity ] ) ) {
                        $reviseIssues[ $issue->id_category ][ 'founds' ][ $issue->issue_severity ] = 1;
                    } else {
                        $reviseIssues[ $issue->id_category ][ 'founds' ][ $issue->issue_severity ]++;
                    }
                }
            }

            $quality_overall = ( $chunkReview->is_pass == null ? null : ( !empty( $chunkReview->is_pass ) ? 'excellent' : 'fail' ) );

        } else {

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

                /**
                 * @var $jobQA Revise_JobQA
                 */
                list( $jobQA, $this->reviseClass ) = $this->featureSet->filter( "overrideReviseJobQA", [ $jobQA, $this->reviseClass ], $this->id_job,
                        $this->password_job,
                        $wStruct->getTotal() );


                if ( $errorCountStruct->thereAreDifferences() ) {
                    $errorCountDao->atomicUpdate( $errorCountStruct );
                    $jobQA->cleanErrorCache();
                }

                $jobQA->retrieveJobErrorTotals();

                $reviseIssues    = $jobQA->getQaData();
                $quality_overall = strtolower( $chunkReview[ 'minText' ] );

            } catch ( Exception $e ) {
                Log::doJsonLog( __METHOD__ . " -> " . $e->getMessage() );
                $this->result[ 'errors' ] [] = [ 'code' => -5, 'message' => "Did not update job error counters." ];

                return;
            }
        }

        $this->result[ 'data' ][ 'message' ]               = 'OK';
        $this->result[ 'data' ][ 'stat_quality' ]          = $reviseIssues;
        $this->result[ 'data' ][ 'overall_quality' ]       = $quality_overall;
        $this->result[ 'data' ][ 'overall_quality_class' ] = $quality_overall;

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