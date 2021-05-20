<?php

use API\V2\Json\QAGlobalWarning;
use API\V2\Json\QALocalWarning;
use SubFiltering\Filter;
use Translations\WarningDao;

class getWarningController extends ajaxController {

    private $__postInput = null;

    /**
     * @var Projects_ProjectStruct
     */
    private $project;

    /**
     * @var Chunks_ChunkStruct
     */
    private $chunk;

    public function __construct() {

        parent::__construct();
        $this->readLoginInfo();

        $filterArgs = [

                'id'             => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'id_job'         => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'src_content'    => [ 'filter' => FILTER_UNSAFE_RAW ],
                'trg_content'    => [ 'filter' => FILTER_UNSAFE_RAW ],
                'password'       => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'token'          => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW
                ],
                'logs'           => [ 'filter' => FILTER_UNSAFE_RAW ],
                'segment_status' => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ]
        ];

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

        $this->project = $this->chunk->getProject();

        $this->loadFeatures();

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

            $this->__segmentWarningsCall();
        }

    }

    private function loadFeatures() {
        $this->featureSet->loadForProject( $this->project );
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
            $result    = WarningDao::getWarningsByJobIdAndPassword( $this->__postInput->id_job, $this->__postInput->password );
            $tMismatch = ( new Segments_SegmentDao() )->getTranslationsMismatches( $this->__postInput->id_job, $this->__postInput->password );
        } catch ( Exception $e ) {
            $this->result[ 'details' ]                = [];
            $this->result[ 'translation_mismatches' ] = [ 'total' => 0, 'mine' => 0, 'list_in_my_job' => [] ];

            return;
        }

        $qa = new QAGlobalWarning( $result, $tMismatch );

        $this->result = array_merge(
                $this->result,
                $qa->render(),
                Utils::getGlobalMessage()
        );

        $this->invokeGlobalWarningsOnFeatures();

    }

    /**
     * Performs a check on single segment
     *
     * @throws \API\V2\Exceptions\AuthenticationError
     * @throws \Exceptions\NotFoundException
     * @throws \Exceptions\ValidationError
     * @throws \TaskRunner\Exceptions\EndQueueException
     * @throws \TaskRunner\Exceptions\ReQueueException
     */
    private function __segmentWarningsCall() {

        $this->result[ 'total' ] = 0;

        $Filter     = Filter::getInstance( $this->chunk->source, $this->chunk->target, $this->featureSet );

        $this->__postInput->src_content = $Filter->fromLayer2ToLayer1( $this->__postInput->src_content );
        $this->__postInput->trg_content = $Filter->fromLayer2ToLayer1( $this->__postInput->trg_content );

        $QA = new QA( $this->__postInput->src_content, $this->__postInput->trg_content );
        $QA->setFeatureSet( $this->featureSet );
        $QA->setSourceSegLang( $this->chunk->source );
        $QA->setTargetSegLang( $this->chunk->target );
        $QA->performConsistencyCheck();

        $this->result = array_merge( $this->result, ( new QALocalWarning( $QA, $this->__postInput->id ) )->render() );

        $this->invokeLocalWarningsOnFeatures();
    }


    private function invokeGlobalWarningsOnFeatures() {

        $this->result = $this->featureSet->filter( 'filterGlobalWarnings', $this->result, [
                'chunk' => $this->chunk,
        ] );

    }

    private function invokeLocalWarningsOnFeatures() {
        $data = [];

        $data = $this->featureSet->filter( 'filterSegmentWarnings', $data, [
                'src_content' => $this->__postInput->src_content,
                'trg_content' => $this->__postInput->trg_content,
                'project'     => $this->project,
                'chunk'       => $this->chunk
        ] );

        $this->result[ 'data' ] = $data;
    }

}
