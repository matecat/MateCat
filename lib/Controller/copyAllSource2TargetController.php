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

        $filterArgs = array(
                'id_job' => array(
                        'filter' => FILTER_SANITIZE_NUMBER_INT
                ),
                'pass'   => array(
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
        );

        $postInput = filter_input_array( INPUT_POST, $filterArgs );

        $this->id_job = $postInput[ 'id_job' ];
        $this->pass   = $postInput[ 'pass' ];

        Log::doLog( "Requested massive copy-source-to-target for job $this->id_job." );

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

        $job_data = getJobData( $this->id_job, $this->pass );

        if ( empty( $job_data ) ) {
            $errorCode = -3;
            $this->addError( $errorCode );

            return;
        }

        $first_seg = $job_data[ 'job_first_segment' ];
        $last_seg  = $job_data[ 'job_last_segment' ];

        try {
            $segments = $this->getNewSegments( $first_seg, $last_seg );
            Log::doLog( "SEGS: " . implode( ",", $segments ) );

            $affected_rows = $this->copySegmentInTranslation( $first_seg, $last_seg );
        } catch ( Exception $e ) {
            $errorCode = -4;

            self::$errorMap[ $errorCode ][ 'internalMessage' ] .= $e->getMessage();

            $this->addError( $errorCode );

            return;
        }
        $this->result[ 'data' ] = array(
                'code'              => 1,
                'segments_modified' => $affected_rows
        );
        Log::doLog( $this->result[ 'data' ] );
    }


    /**
     * Copies the segments.segment field into segment_translations.translation
     * and sets the segment status to <b>DRAFT</b>.
     * This operation is made only for the segments in <b>NEW</b> status
     *
     * @param $first_seg int
     * @param $last_seg  int
     */
    private function copySegmentInTranslation( $first_seg, $last_seg ) {

        $query = "update segment_translations st
                    join segments s on st.id_segment = s.id
                    join jobs j on st.id_job = j.id
                    set st.translation = s.segment,
                    st.status = 'DRAFT',
                    st.translation_date = now()
                    where st.status = 'NEW'
                    and j.id = %d
                    and j.password = '%s'
                    and st.id_segment between %d and %d";

        $db = Database::obtain();

        $result = $db->query(
                sprintf(
                        $query,
                        $this->id_job,
                        $this->pass,
                        $first_seg,
                        $last_seg
                )
        );

        if ( $result !== true ) {
            throw new Exception( $db->error, -1 );
        }

        return $db->affected_rows;
    }

    /**
     * Copies the segments.segment field into segment_translations.translation
     * and sets the segment status to <b>DRAFT</b>.
     * This operation is made only for the segments in <b>NEW</b> status
     *
     * @param $first_seg int
     * @param $last_seg  int
     */
    private function getNewSegments( $first_seg, $last_seg ) {

        $query = "select s.id from segment_translations st
                    join segments s on st.id_segment = s.id
                    join jobs j on st.id_job = j.id
                    where st.status = 'NEW'
                    and j.id = %d
                    and j.password = '%s'
                    and st.id_segment between %d and %d";

        $db = Database::obtain();

        $result = $db->fetch_array(
                sprintf(
                        $query,
                        $this->id_job,
                        $this->pass,
                        $first_seg,
                        $last_seg
                )
        );


        //Array_column() is not supported on PHP 5.4, so i'll rewrite it
        if ( !function_exists( 'array_column' ) ) {
            $result = Utils::array_column( $result, 'id' );
        } else {
            $result = array_column( $result, 'id' );
        }

        return $result;
    }

    private function setErrorMap() {
        $generalOutputError = "Error while copying sources to targets. Please contact support@matecat.com";

        self::$errorMap = array(
                "-1" => array(
                        'internalMessage' => "Empty id job",
                        'outputMessage'   => $generalOutputError
                ),
                "-2" => array(
                        'internalMessage' => "Empty job password",
                        'outputMessage'   => $generalOutputError
                ),
                "-3" => array(
                        'internalMessage' => "Wrong id_job-password couple. Job not found",
                        'outputMessage'   => $generalOutputError
                ),
                "-4" => array(
                        'internalMessage' => "Error in copySegmentInTranslation: ",
                        'outputMessage'   => $generalOutputError
                )
        );
    }

    /**
     * @param $errorCode int
     */
    private function addError( $errorCode ) {
        Log::doLog( $this->getErrorMessage( $errorCode ) );
        $this->result[ 'errors' ][] = array(
                'code'    => $errorCode,
                'message' => $this->getOutputErrorMessage( $errorCode )
        );
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