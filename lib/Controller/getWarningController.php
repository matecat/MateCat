<?

class getWarningController extends ajaxController {

    private $__postInput = null;

    public function __destruct() {
    }

    public function __construct() {

        parent::__construct();

        $filterArgs = array(

            'id'          => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'id_job'      => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'src_content' => array( 'filter' => FILTER_UNSAFE_RAW ),
            'trg_content' => array( 'filter' => FILTER_UNSAFE_RAW ),
            'password'    => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'token'       => array( 'filter' => FILTER_SANITIZE_STRING,
                                    'flags'  => FILTER_FLAG_STRIP_LOW ),
            'logs'        => array( 'filter' => FILTER_UNSAFE_RAW),
            'glossaryList' => array( 'filter' => FILTER_CALLBACK, 'options' => array('self','filterString') )
        );

        $this->__postInput = (object)filter_input_array( INPUT_POST, $filterArgs );

        if( !empty( $this->__postInput->logs ) && $this->__postInput->logs != '[]' ){
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
        if ( empty( $this->__postInput->id ) ) {
            $this->__globalWarningsCall();
        } else {
            $this->__postInput->src_content = CatUtils::view2rawxliff( $this->__postInput->src_content );
            $this->__postInput->trg_content = CatUtils::view2rawxliff( $this->__postInput->trg_content );
            $this->__segmentWarningsCall();
        }

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

        $result = getWarning( $this->__postInput->id_job, $this->__postInput->password );

        foreach ( $result as $position => &$item ) {

            $item = $item[ 'id_segment' ];

        }

        $this->result[ 'details' ] = array_values($result);
        $this->result[ 'token' ]   = $this->__postInput->token;

//        $msg = 'MateCat will be undergoing scheduled maintenance starting on Saturday, December 13 at 11:50 PM CEST. MateCat will be unavailable for approximately 4 hours.<br /> We apologize for any inconvenience. For any questions, contact us support@matecat.com.';
//        $this->result['messages']  = '[{"msg":"' . $msg . '", "token":"' . md5($msg) . '", "expire":"2014-12-14 04:00:00"}]';


        $tMismatch = getTranslationsMismatches( $this->__postInput->id_job, $this->__postInput->password );

//        Log::doLog( $tMismatch );

        $result = array( 'total' => count( $tMismatch ), 'mine' => 0, 'list_in_my_job' => array() );

        foreach ( $tMismatch as $row ){
            if( !empty( $row['first_of_my_job'] ) ){
                $result['mine']++;
                $result['list_in_my_job'][] = $row['first_of_my_job'];
//                $result['list_in_my_job'][] = array_shift( explode( "," , $row['first_of_my_job'] ) );

                //append to global list
                $this->result[ 'details' ][] = $row['first_of_my_job'];
//                $this->result[ 'details' ] = array_merge( $this->result[ 'details' ], explode( "," , $row['first_of_my_job'] )  )

            }
        }

        //???? php maps internally numerical keys of array_unique as string so with json_encode
        //it become an object and not an array!!
        $this->result[ 'details' ] = array_values( array_unique( $this->result[ 'details' ] ) );
        $this->result[ 'translation_mismatches' ] = $result;

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
        $QA->performGlossaryCheck($this->__postInput->glossaryList );

        if ( $QA->thereAreNotices() ) {
//        if ( $QA->thereAreErrors() ) {
            $this->result[ 'details' ]                 = array();
            $this->result[ 'details' ][ 'id_segment' ] = $this->__postInput->id;
//            $this->result[ 'details' ][ 'warnings' ]   = $QA->getErrorsJSON();
//            $this->result[ 'total' ]                                             = count( $QA->getErrors() );
            $this->result[ 'details' ][ 'warnings' ]                = $QA->getNoticesJSON();
            $this->result[ 'details' ][ 'tag_mismatch' ]            = $QA->getMalformedXmlStructs();
            $this->result[ 'details' ][ 'tag_mismatch' ][ 'order' ] = $QA->getTargetTagPositionError();
            $this->result[ 'total' ]                                = count( $QA->getNotices() );
//temp
			
//            Log::doLog($this->__postInput->trg_content);
//            Log::doLog($this->result);

        }

    }

    private static  function filterString($glossaryWord) {
        $glossaryWord = (string) $glossaryWord;
        $glossaryWord = filter_var(
                $glossaryWord,
                FILTER_SANITIZE_STRING,
                array( 'flags' => FILTER_FLAG_STRIP_LOW )
        );

        return $glossaryWord;
    }


}

?>
