<?
include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . "/cat.class.php";
include_once INIT::$UTILS_ROOT . "/QA.php";

class getWarningController extends ajaxcontroller {

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
            'token'       => array( 'filter' => FILTER_SANITIZE_STRING,
                                    'flags'  => FILTER_FLAG_STRIP_LOW ),

        );

        $this->__postInput = (object)filter_input_array( INPUT_POST, $filterArgs );

        $this->__postInput->src_content = CatUtils::view2rawxliff( $this->__postInput->src_content );
        $this->__postInput->trg_content = CatUtils::view2rawxliff( $this->__postInput->trg_content );

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
     *         [total] => 1
     *         [id_segment] =>
     *         [serialized_errors_list] => [{"outcome":1000,"debug":"Tag mismatch"}]
     *     ),
     * [1] => Array
     *     (
     *         [total] => 1
     *         [id_segment] => 2224903
     *         [serialized_errors_list] => [{"outcome":1000,"debug":"Tag mismatch"}]
     *     ),
     * )
     * </pre>
     */
    private function __globalWarningsCall() {
        $result                  = getWarning( $this->__postInput->id_job );
        $_total                  = array_shift( $result );
        $this->result[ 'total' ] = (int)$_total[ 'total' ];

        $_keys = array();
        foreach ( $result as $key => &$item ) {
            if ( $item[ 'warnings' ] == '01' || $item[ 'warnings' ] == "" ) {
                //backward compatibility
                //TODO Remove after some days/month/year of use of QA class.
                $item[ 'warnings' ] = '[{"outcome":3,"debug":"bad target xml"}]';
            }
            unset( $item[ 'total' ] );
            $_keys[ ] = $item[ 'id_segment' ];
        }

        $result                    = @array_combine( $_keys, $result );
        $this->result[ 'details' ] = $result;
        $this->result[ 'token' ]   = $this->__postInput->token;
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
            $this->result[ 'details' ]                                           = array();
            $this->result[ 'details' ][ $this->__postInput->id ]                 = array();
            $this->result[ 'details' ][ $this->__postInput->id ][ 'id_segment' ] = $this->__postInput->id;
            $this->result[ 'details' ][ $this->__postInput->id ][ 'warnings' ]   = $QA->getWarningsJSON();
            $this->result[ 'total' ]                                             = count( $QA->getWarnings() );
        }

    }

}

?>