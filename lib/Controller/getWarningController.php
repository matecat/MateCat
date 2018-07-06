<?php

use API\V2\Json\QAGlobalWarning;
use API\V2\Json\QALocalWarning;
use API\V2\Json\SegmentTranslationMismatches;

class getWarningController extends ajaxController {

    private $__postInput = null;

    /**
     * @var Projects_ProjectStruct
     */
    private $project ;

    /**
     * @var Chunks_ChunkStruct
     */
    private $chunk ;

    public function __construct() {

        parent::__construct();

        $filterArgs = array(

                'id'             => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'id_job'         => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'src_content'    => array( 'filter' => FILTER_UNSAFE_RAW ),
                'trg_content'    => array( 'filter' => FILTER_UNSAFE_RAW ),
                'password'       => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'token'          => array(
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW
                ),
                'logs'           => array( 'filter' => FILTER_UNSAFE_RAW ),
                'segment_status' => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                )
        );

        $this->__postInput = (object)filter_input_array( INPUT_POST, $filterArgs );

        /**
         * Update 2015/08/11, roberto@translated.net
         * getWarning needs the segment status too because of a bug:
         *   sometimes the client calls getWarning and sends an empty trg_content
         *   because the suggestion has not been loaded yet.
         *   This happens only if segment is in status NEW
         */
        if ( empty( $this->__postInput->segment_status ) ) {
            $this->__postInput->segment_status = 'draft';
        }

        if ( !empty( $this->__postInput->logs ) && $this->__postInput->logs != '[]' ) {
            Log::$fileName = 'clientLog.log';
            Log::doLog( json_decode( $this->__postInput->logs ) );
            Log::$fileName = 'log.txt';
        }

    }

    /**
     * Return to Javascript client the JSON error list in the form:
     *
     * <pre>
     * array(
     *       [total] => 1
     *       [details] => Array
     *       (
     *           ['2224860'] => Array
     *               (
     *                   [id_segment] => 2224860
     *                   [warnings] => '[{"outcome":1000,"debug":"Tag mismatch"}]'
     *               )
     *       )
     *      [token] => 'token'
     * )
     * </pre>
     *
     */
    public function doAction() {

        $this->chunk = Chunks_ChunkDao::getByIdAndPassword(
                $this->__postInput->id_job,
                $this->__postInput->password
        );

        $this->project = $this->chunk->getProject() ;

        $this->loadFeatures() ;

        if ( empty( $this->__postInput->src_content ) ) {
            $this->__globalWarningsCall();
        } else {
            /**
             * Update 2015/08/11, roberto@translated.net
             * getWarning needs the segment status too because of a bug:
             *   sometimes the client calls getWarning and sends an empty trg_content
             *   because the suggestion has not been loaded yet.
             *   This happens only if segment is in status NEW
             */
            if ( $this->__postInput->segment_status == 'new' &&
                    empty( $this->__postInput->trg_content )
            ) {
                return;
            }

            $this->__postInput->src_content = CatUtils::view2rawxliff( $this->__postInput->src_content );
            $this->__postInput->trg_content = CatUtils::view2rawxliff( $this->__postInput->trg_content );
            $this->__segmentWarningsCall();
        }

    }

    private function loadFeatures() {
        $this->featureSet->loadForProject( $this->project ) ;
    }

    /**
     *
     * getWarning $query are in the form:
     * <pre>
     * Array
     * (
     * [0] => Array
     *     (
     *         [id_segment] => 2224900
     *     ),
     * [1] => Array
     *     (
     *         [id_segment] => 2224903
     *     ),
     * )
     * </pre>
     */
    private function __globalWarningsCall() {

        $this->result[ 'token' ] = $this->__postInput->token;

        try {
            $result = getWarning( $this->__postInput->id_job, $this->__postInput->password );
            $tMismatch = getTranslationsMismatches( $this->__postInput->id_job, $this->__postInput->password );
        } catch ( Exception $e ) {
            $this->result[ 'details' ]                = array();
            $this->result[ 'translation_mismatches' ] = array( 'total' => 0, 'mine' => 0, 'list_in_my_job' => array() );

            return;
        }

        $this->result = array_merge(
                $this->result,
                ( new QAGlobalWarning( $result, $tMismatch ) )->render(),
                Utils::getGlobalMessage()
        );

        $this->invokeGlobalWarningsOnFeatures();

    }

    /**
     * Performs a check on single segment
     *
     */
    private function __segmentWarningsCall() {

        $this->result[ 'total' ]   = 0;

        $QA = new QA( $this->__postInput->src_content, $this->__postInput->trg_content );
        $QA->performConsistencyCheck();

        $this->result = array_merge( $this->result, ( new QALocalWarning( $QA, $this->__postInput->id ) )->render() );

        $this->invokeLocalWarningsOnFeatures();
    }


    private function invokeGlobalWarningsOnFeatures() {

        $this->result = $this->featureSet->filter( 'filterGlobalWarnings', $this->result, array(
                'chunk'       => $this->chunk,
        ) );

    }

    private function invokeLocalWarningsOnFeatures() {
        $data = array( );

        $data = $this->featureSet->filter( 'filterSegmentWarnings', $data, array(
                'src_content' => $this->__postInput->src_content,
                'trg_content' => $this->__postInput->trg_content,
                'project'     => $this->project,
                'chunk'       => $this->chunk
        ) );

        $this->result['data'] = $data ;
    }

}
