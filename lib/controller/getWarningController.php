<?
include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . "/CatUtils.php";
include_once INIT::$UTILS_ROOT . "/QA.php";

class getWarningController extends ajaxController {

    private $__postInput = null;

    public function __destruct() {
    }

    public function __construct() {

        $this->disableSessions();
        parent::__construct();

        $filterArgs = array(

            'id'          => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'id_job'      => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'src_content' => array( 'filter' => FILTER_UNSAFE_RAW ),
            'trg_content' => array( 'filter' => FILTER_UNSAFE_RAW ),
            'password'    => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'token'       => array( 'filter' => FILTER_SANITIZE_STRING,
                                    'flags'  => FILTER_FLAG_STRIP_LOW ),

        );

        $this->__postInput = (object)filter_input_array( INPUT_POST, $filterArgs );

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

            //PATCH - REMOVE WHITESPACES FROM GLOBAL WARNING ( Backward compatibility )
//            $serialized_err = json_decode( $item['serialized_errors_list'] );
//
//            $foundTagMismatch = false;
//            foreach( $serialized_err as $k => $error ){
//
//                switch ( $error->outcome ) {
//                    case QA::ERR_TAG_MISMATCH:
//                    case QA::ERR_TAG_ID:
//                    case QA::ERR_UNCLOSED_X_TAG:
//                        $foundTagMismatch = true;
//                        break;
//                }
//
//            }
//
//            if( !$foundTagMismatch ){
//                unset( $result[$position] );
//            } else {
//                $item = $item[ 'id_segment' ];
//            }
            //PATCH - REMOVE WHITESPACES FROM GLOBAL WARNING ( Backward compatibility )

            $item = $item[ 'id_segment' ];

        }

        $this->result[ 'details' ] = array_values($result);
        $this->result[ 'token' ]   = $this->__postInput->token;
	//        $this->result['messages']  = '[{"msg":"Test message 1","token":"token1","expire":"2014-04-03 00:00"},{"msg":"Test message 2","token":"token2","expire":"2014-04-04 12:00"}]';
//	    $this->result['messages']  = '[{"msg":"Test message 1","token":"token1","expire":"2014-05-19 11:28"},{"msg":"Test message 2","token":"token2","expire":"2014-04-04 12:00"}]';

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
        if ( $QA->thereAreWarnings() ) {
//        if ( $QA->thereAreErrors() ) {
            $this->result[ 'details' ]                 = array();
            $this->result[ 'details' ]                 = array();
            $this->result[ 'details' ][ 'id_segment' ] = $this->__postInput->id;
//            $this->result[ 'details' ][ 'warnings' ]   = $QA->getErrorsJSON();
//            $this->result[ 'total' ]                                             = count( $QA->getErrors() );
            $this->result[ 'details' ][ 'warnings' ]                = $QA->getWarningsJSON();
            $this->result[ 'details' ][ 'tag_mismatch' ]            = $QA->getMalformedXmlStructs();
            $this->result[ 'details' ][ 'tag_mismatch' ][ 'order' ] = $QA->getTargetTagPositionError();
            $this->result[ 'total' ]                                = count( $QA->getWarnings() );
//temp
			
//            Log::doLog($this->__postInput->trg_content);
//            Log::doLog($this->result);

        }

    }

}

?>
