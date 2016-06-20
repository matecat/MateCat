<?php

class getTagProjectionController extends ajaxController {

    protected $__postInput = array();

    protected $password   = "";
    protected $suggestion = "";
    protected $source;
    protected $target;
    protected $source_lang;
    protected $target_lang;
    protected $id_job;

    protected $old_logFile;


    public function __construct() {

        $this->old_logFile = \Log::$fileName;

        parent::__construct();

        $filterArgs = array(
                'id_job'      => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'password'    => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'source'      => array( 'filter' => FILTER_UNSAFE_RAW ),
                'target'      => array( 'filter' => FILTER_UNSAFE_RAW ),
                'suggestion'  => array( 'filter' => FILTER_UNSAFE_RAW ),
                'source_lang' => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'target_lang' => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
        );

        $this->__postInput = filter_input_array( INPUT_POST, $filterArgs );

        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI Test scripts
        //$this->__postInput = filter_var_array( $_POST, $filterArgs );

        $this->id_job      = $this->__postInput[ 'id_job' ];
        $this->password    = $this->__postInput[ 'password' ];
        $this->source      = $this->__postInput[ 'source' ];
        $this->target      = $this->__postInput[ 'target' ];
        $this->source_lang = $this->__postInput[ 'source_lang' ];
        $this->target_lang = $this->__postInput[ 'target_lang' ];
        $this->suggestion  = $this->__postInput[ 'suggestion' ];

        \Log::$fileName = 'tagProjection.log';

    }

    public function doAction() {

        if ( is_null( $this->source ) || $this->source === '' ) {
            $this->result[ 'errors' ][] = array( "code" => -1, "message" => "missing source segment" );
        }

        if ( is_null( $this->target ) || $this->target === '' ) {
            $this->result[ 'errors' ][] = array( "code" => -2, "message" => "missing target segment" );
        }

        if ( empty( $this->source_lang ) ) {
            $this->result[ 'errors' ][] = array( "code" => -3, "message" => "missing source lang" );
        }

        if ( empty( $this->target_lang ) ) {
            $this->result[ 'errors' ][] = array( "code" => -2, "message" => "missing target lang" );
        }

        if ( empty( $this->id_job ) ) {
            $this->result[ 'errors' ][] = array( "code" => -4, "message" => "id_job not valid" );

            $msg = "\n\n Critical. Quit. \n\n " . var_export( array_merge( $this->result, $_POST ), true );
            Log::doLog( $msg );
            Utils::sendErrMailReport( $msg );

            // critical. Quit.
            return -1;
        }

        //get Job Infos, we need only a row of jobs ( split )
        $job_data = getJobData( (int)$this->id_job, $this->password );

        $pCheck = new AjaxPasswordCheck();
        //check for Password correctness
        if ( empty( $job_data ) ) {
            $this->result[ 'errors' ][] = array( "code" => -101, "message" => "error fetching job data" );
        }

        if ( !$pCheck->grantJobAccessByJobData( $job_data, $this->password ) ) {
            $this->result[ 'errors' ][] = array( "code" => -10, "message" => "wrong password" );
        }

        if ( !empty( $this->result[ 'errors' ] ) ) {
            $msg = "\n\n Error \n\n " . var_export( array_merge( $this->result, $_POST ), true );
            Log::doLog( $msg );
            Utils::sendErrMailReport( $msg );

            return -1;
        }

        $config = array();

        $config[ 's' ]    = CatUtils::view2rawxliff( $this->source );
        $config[ 't' ]    = CatUtils::view2rawxliff( $this->target );
        $config[ 'sl' ]   = CatUtils::view2rawxliff( $this->source_lang );
        $config[ 'tl' ]   = CatUtils::view2rawxliff( $this->target_lang );
        $config[ 'hint' ] = CatUtils::view2rawxliff( $this->suggestion );

        $this->getTagProjection();

    }

    public function getTagProjection() {

        //FIXME For now get Directly the engine because it is not in the database and there is not a Model for it
        $engine = new Engines_MMT( new EnginesModel_EngineStruct() );

        $config                  = array();
        $config[ 'source' ]      = CatUtils::view2rawxliff( $this->source );
        $config[ 'target' ]      = CatUtils::view2rawxliff( $this->target );
        $config[ 'source_lang' ] = $this->source_lang;
        $config[ 'target_lang' ] = $this->target_lang;
        $config[ 'suggestion' ]  = CatUtils::view2rawxliff( $this->suggestion );

        $result = $engine->getTagProjection( $config );
        if( empty( $result->error ) ){
            $this->result[ 'data' ][ 'translation' ] = $result->responseData;
            $this->result[ 'code' ] = 0;
            $this->logTagProjection();
        } else {
            $this->result[ 'code' ] = $result->error->code;
            $this->result[ 'errors' ] = $result->error;
            $this->logTagProjection( $result->error );
        }

        \Log::$fileName = $this->old_logFile;

    }

    public function logTagProjection( $msg = null ) {

        if( !$msg ){
            \Log::doLog( $this->result[ 'data' ] );
        } else {
            \Log::doLog( $msg );
        }

    }
}


