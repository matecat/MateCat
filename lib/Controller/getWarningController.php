<?php

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

    /** @var FeatureSet */
    private $feature_set ;

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
        $this->feature_set = new FeatureSet() ;
        $this->feature_set->loadFromIdCustomer( $this->project->id_customer ) ;
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
        } catch ( Exception $e ) {
            $this->result[ 'details' ]                = array();
            $this->result[ 'translation_mismatches' ] = array( 'total' => 0, 'mine' => 0, 'list_in_my_job' => array() );

            return;
        }

        foreach ( $result as $position => &$item ) {
            $item = $item[ 'id_segment' ];
        }

        $this->result[ 'messages' ] = $this->getGlobalMessage();

        $this->result[ 'details' ][ 'tag_issues' ] = array_values( $result );
        $tMismatch                 = getTranslationsMismatches( $this->__postInput->id_job, $this->__postInput->password );

        $result = array( 'total' => count( $tMismatch ), 'mine' => 0, 'list_in_my_job' => array() );

        foreach ( $tMismatch as $row ) {
            if ( !empty( $row[ 'first_of_my_job' ] ) ) {
                $result[ 'mine' ]++;
                $result[ 'list_in_my_job' ][] = $row[ 'first_of_my_job' ];

                //append to global list
                $this->result[ 'details' ][ 'translation_mismatches' ][] = $row[ 'first_of_my_job' ];

            }
        }

        $this->result[ 'translation_mismatches' ] = $result;

        $this->invokeGlobalWarningsOnFeatures();
    }

    /**
     * Performs a check on single segment
     *
     */
    private function __segmentWarningsCall() {

        $this->result[ 'details' ] = null;
        $this->result[ 'token' ]   = $this->__postInput->token;
        $this->result[ 'total' ]   = 0;

        $QA = new QA( $this->__postInput->src_content, $this->__postInput->trg_content );
        $QA->performConsistencyCheck();

        if ( $QA->thereAreNotices() ) {
            $this->result[ 'details' ]                 = array();
            $this->result[ 'details' ][ 'id_segment' ] = $this->__postInput->id;
            $this->result[ 'details' ][ 'warnings' ]                = $QA->getNoticesJSON();
            $this->result[ 'details' ][ 'tag_mismatch' ]            = $QA->getMalformedXmlStructs();
            $this->result[ 'details' ][ 'tag_mismatch' ][ 'order' ] = $QA->getTargetTagPositionError();
            $this->result[ 'total' ]                                = count( $QA->getNotices() );
        }

        $this->invokeLocalWarningsOnFeatures();
    }


    private function invokeGlobalWarningsOnFeatures() {
        $data = array( );

        $data = $this->feature_set->filter( 'filterGlobalWarnings', $data, array(
                'chunk'       => $this->chunk
        ) );

        $this->result['data'] = $data ;
    }

    private function invokeLocalWarningsOnFeatures() {
        $data = array( );

        $data = $this->feature_set->filter( 'filterSegmentWarnings', $data, array(
                'src_content' => $this->__postInput->src_content,
                'trg_content' => $this->__postInput->trg_content,
                'project'     => $this->project,
                'chunk'       => $this->chunk
        ) );

        $this->result['data'] = $data ;
    }

    private static function filterString( $glossaryWord ) {
        $glossaryWord = (string)$glossaryWord;
        $glossaryWord = filter_var(
                $glossaryWord,
                FILTER_SANITIZE_STRING,
                array( 'flags' => FILTER_FLAG_STRIP_LOW )
        );

        return empty( $glossaryWord ) ? '' : $glossaryWord;
    }

    private function getGlobalMessage(){
        if ( file_exists( INIT::$ROOT . "/inc/.globalmessage.ini" ) ) {
            $globalMessage              = parse_ini_file( INIT::$ROOT . "/inc/.globalmessage.ini" );
            return sprintf(
                            '[{"msg":"%s", "token":"%s", "expire":"%s"}]',
                            $globalMessage[ 'message' ],
                            md5( $globalMessage[ 'message' ] ),
                            $globalMessage[ 'expire' ]
                    );
        }
        return null;
    }
}

?>
