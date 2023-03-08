<?php

use Features\ReviewExtended\ReviewUtils;
use Features\TranslationVersions\Handlers\TranslationEventsHandler;
use Features\TranslationVersions\Model\TranslationEvent;

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 30/07/15
 * Time: 15.10
 */
class copyAllSource2TargetController extends ajaxController {

    private $id_job;
    private $pass;

    private static $errorMap;
    private        $revisionNumber;

    protected function __construct() {
        parent::__construct();

        $this->setErrorMap();

        $filterArgs = [
                'id_job'          => [
                        'filter' => FILTER_SANITIZE_NUMBER_INT
                ],
                'pass'            => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'revision_number' => [
                        'filter' => FILTER_SANITIZE_NUMBER_INT
                ],
        ];

        $postInput = filter_input_array( INPUT_POST, $filterArgs );

        $this->id_job         = $postInput[ 'id_job' ];
        $this->pass           = $postInput[ 'pass' ];
        $this->revisionNumber = $postInput[ 'revision_number' ];

        Log::doJsonLog( "Requested massive copy-source-to-target for job $this->id_job." );

        if ( empty( $this->id_job ) ) {
            $errorCode = -1;
            $this->addError( $errorCode );

        }
        if ( empty( $this->pass ) ) {
            $errorCode = -2;
            $this->addError( $errorCode );
        }
    }


    /**
     * When Called it perform the controller action to retrieve/manipulate data
     *
     * @return mixed|void
     * @throws ReflectionException
     */
    function doAction() {
        if ( !empty( $this->result[ 'errors' ] ) ) {
            return;
        }

        $job_data = Jobs_JobDao::getByIdAndPassword( $this->id_job, $this->pass );

        if ( empty( $job_data ) ) {
            $errorCode = -3;
            $this->addError( $errorCode );

            return;
        }

        $this->_saveEventsAndUpdateTranslations( $job_data->id, $job_data->password );
    }

    /**
     * @param $job_id
     * @param $password
     *
     * @throws ReflectionException
     * @throws \Exceptions\NotFoundException
     */
    private function _saveEventsAndUpdateTranslations( $job_id, $password ) {
        if ( !empty( $this->result[ 'errors' ] ) ) {
            return;
        }

        $job_data = Jobs_JobDao::getByIdAndPassword( $job_id, $password );

        if ( empty( $job_data ) ) {
            $errorCode = -3;
            $this->addError( $errorCode );

            return;
        }

        // BEGIN TRANSACTION
        $database = Database::obtain();
        $database->begin();

        $chunk    = Chunks_ChunkDao::getByIdAndPassword( $job_id, $password );
        $features = $chunk->getProject()->getFeaturesSet();

        $batchEventCreator = new TranslationEventsHandler( $chunk );
        $batchEventCreator->setFeatureSet( $features );
        $batchEventCreator->setProject( $chunk->getProject() );

        $source_page = ReviewUtils::revisionNumberToSourcePage( $this->revisionNumber );
        $segments    = $chunk->getSegments();

        $affected_rows = 0;
        foreach ( $segments as $segment ) {

            $segment_id = (int)$segment->id;
            $chunk_id   = (int)$chunk->id;

            $old_translation = Translations_SegmentTranslationDao::findBySegmentAndJob( $segment_id, $chunk_id );

            if ( empty( $old_translation ) || ( $old_translation->status !== Constants_TranslationStatus::STATUS_NEW ) ) {
                //no segment found
                continue;
            }

            $new_translation              = clone $old_translation;
            $new_translation->translation = $segment->segment;
            $new_translation->status      = Constants_TranslationStatus::STATUS_DRAFT;
            $new_translation->translation_date      = date( "Y-m-d H:i:s" );


            try {
                $affected_rows += Translations_SegmentTranslationDao::updateTranslationAndStatusAndDate( $new_translation );
            } catch ( Exception $e ) {
                $errorCode                                         = -4;
                self::$errorMap[ $errorCode ][ 'internalMessage' ] .= $e->getMessage();
                $this->addError( $errorCode );
                $database->rollback();
                return;
            }

            if ( $chunk->getProject()->hasFeature( Features::TRANSLATION_VERSIONS ) ) {
                $segmentTranslationEventModel = new TranslationEvent( $old_translation, $new_translation, $this->user, $source_page );
                $batchEventCreator->addEvent( $segmentTranslationEventModel );
            }
        }

        // save all events
        $batchEventCreator->save();

        if ( !empty( $params[ 'segment_ids' ] ) ) {
            $counter = new WordCount_CounterModel();
            $counter->initializeJobWordCount( $chunk->id, $chunk->password );
        }

        Log::doJsonLog( 'Segment Translation events saved completed' );

        $this->result[ 'data' ] = [
                'code'              => 1,
                'segments_modified' => $affected_rows
        ];

        Log::doJsonLog( $this->result[ 'data' ] );

        $database->commit(); // COMMIT TRANSACTION
    }

    private function setErrorMap() {
        $generalOutputError = "Error while copying sources to targets. Please contact support@matecat.com";

        self::$errorMap = [
                "-1" => [
                        'internalMessage' => "Empty id job",
                        'outputMessage'   => $generalOutputError
                ],
                "-2" => [
                        'internalMessage' => "Empty job password",
                        'outputMessage'   => $generalOutputError
                ],
                "-3" => [
                        'internalMessage' => "Wrong id_job-password couple. Job not found",
                        'outputMessage'   => $generalOutputError
                ],
                "-4" => [
                        'internalMessage' => "Error in copySegmentInTranslation: ",
                        'outputMessage'   => $generalOutputError
                ]
        ];
    }

    /**
     * @param $errorCode int
     */
    private function addError( $errorCode ) {
        Log::doJsonLog( $this->getErrorMessage( $errorCode ) );
        $this->result[ 'errors' ][] = [
                'code'    => $errorCode,
                'message' => $this->getOutputErrorMessage( $errorCode )
        ];
    }

    /**
     * @param $errorCode int
     *
     * @return string
     */
    private function getErrorMessage( $errorCode ) {
        if ( array_key_exists( $errorCode, self::$errorMap ) ) {
            return self::$errorMap[ $errorCode ][ 'internalMessage' ];
        }

        return "";
    }

    /**
     * @param $errorCode int
     *
     * @return string
     */
    private function getOutputErrorMessage( $errorCode ) {
        if ( array_key_exists( $errorCode, self::$errorMap ) ) {
            return self::$errorMap[ $errorCode ][ 'outputMessage' ];
        }

        return "";
    }
}