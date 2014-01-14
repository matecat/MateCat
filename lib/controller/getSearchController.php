<?php

include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . "/cat.class.php";
include_once INIT::$UTILS_ROOT . '/AjaxPasswordCheck.php';

class getSearchController extends ajaxcontroller {

    private $job;
    private $token;
    private $password;
    private $source;
    private $target;
    private $status;
    private $replace;
    private $function; //can be search, replace
    private $matchCase;
    private $exactMatch;

    private $queryParams = array();

    public function __construct() {

        $this->disableSessions();
        parent::__construct();

        $filterArgs = array(
            'function'    => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
            'job'         => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'token'       => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
            'source'      => array( 'filter' => FILTER_UNSAFE_RAW ),
            'target'      => array( 'filter' => FILTER_UNSAFE_RAW ),
            'status'      => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'replace'     => array( 'filter' => FILTER_UNSAFE_RAW ),
            'password'    => array( 'filter' => FILTER_UNSAFE_RAW ),
            'matchcase'   => array( 'filter' => FILTER_VALIDATE_BOOLEAN ),
            'exactmatch'  => array( 'filter' => FILTER_VALIDATE_BOOLEAN ),
        );

        $__postInput     = filter_input_array( INPUT_POST, $filterArgs );

        $this->function   = $__postInput[ 'function' ]; //can be: search / replace
        $this->job        = $__postInput[ 'job' ];
        $this->token      = $__postInput[ 'token' ];
        $this->source     = $__postInput[ 'source' ];
        $this->target     = $__postInput[ 'target' ];
        $this->status     = strtolower( $__postInput[ 'status' ] );
        $this->replace    = $__postInput[ 'replace' ];
        $this->password   = $__postInput[ 'password' ];
        $this->matchCase  = $__postInput[ 'matchcase' ];
        $this->exactMatch = $__postInput[ 'exactmatch' ];

        if (empty($this->status)) {
            $this->status = "all";
        }

        switch( $this->status ) {
            case 'translated':
            case 'approved':
            case 'rejected':
            case 'draft':
            case 'new':
                break;
            default:
                $this->status = "all";
                break;
        }

        $this->queryParams = new ArrayObject( array(
            'job'         => $this->job,
            'key'         => null,
            'src'         => null,
            'trg'         => null,
            'status'      => $this->status,
            'replacement' => $this->replace,
            'matchCase'   => $this->matchCase,
            'exactMatch'  => $this->exactMatch,
        ) );

    }

    public function doAction() {
        $this->result['token'] = $this->token;
        if (empty($this->job)) {
            $this->result['error'][] = array("code" => -2, "message" => "missing id job");
            return;
        }
        //get Job Infos
        $job_data = getJobData((int) $this->job);

        $pCheck = new AjaxPasswordCheck();
        //check for Password correctness
        if (!$pCheck->grantJobAccessByJobData($job_data, $this->password)) {
            $this->result['error'][] = array("code" => -10, "message" => "wrong password");
            return;
        }

        switch ($this->function) {
            case 'find':
                $this->doSearch();
                break;
            case 'replaceAll':
                $this->doReplaceAll();
                break;
            default :
                $this->result['error'][] = array("code" => -11, "message" => "unknown  function. Use find or replace");
                return;
        }
    }

    private function doSearch() {

        if (!empty($this->source) and !empty($this->target)) {
            $this->queryParams['key'] = 'coupled';
            $this->queryParams['src'] =  $this->source;
            $this->queryParams['trg'] =  $this->target;
        } else if (!empty($this->source)) {
            $this->queryParams['key'] = 'source';
            $this->queryParams['src'] = $this->source;
        } else if (!empty($this->target)) {
            $this->queryParams['key'] = 'target';
            $this->queryParams['trg'] =  $this->target;
        } elseif( empty($this->source) and empty($this->target) ){
            $this->queryParams['key'] = 'status_only';
        }

        $res = doSearchQuery( $this->queryParams );

        if (is_numeric($res) and $res < 0) {
            $this->result['error'][] = array("code" => -1000, "message" => "internal error: see the log");
            return;
        }
        $this->result['total']    = $res['count'];
        $this->result['segments'] = $res['sidlist'];
    }

    private function doReplaceAll(){

    }

}
