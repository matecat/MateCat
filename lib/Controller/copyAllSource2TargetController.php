<?php

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

    protected function __construct() {
        parent::__construct();

        $this->setErrorMap();

        $filterArgs = [
                'id_job' => [
                        'filter' => FILTER_SANITIZE_NUMBER_INT
                ],
                'pass'   => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
        ];

        $postInput = filter_input_array( INPUT_POST, $filterArgs );

        $this->id_job = $postInput[ 'id_job' ];
        $this->pass   = $postInput[ 'pass' ];

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
     * @return mixed
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

        try {
            $affected_rows = Translations_SegmentTranslationDao::copyAllSourceToTargetForJob( $job_data );
        } catch ( Exception $e ) {
            $errorCode                                         = -4;
            self::$errorMap[ $errorCode ][ 'internalMessage' ] .= $e->getMessage();
            $this->addError( $errorCode );

            return;
        }
        $this->result[ 'data' ] = [
                'code'              => 1,
                'segments_modified' => $affected_rows
        ];
        Log::doJsonLog( $this->result[ 'data' ] );
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