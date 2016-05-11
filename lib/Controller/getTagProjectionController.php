<?php


class getTagProjectionController extends ajaxController {

    protected $__postInput = array();

    protected $password = "";
    protected $suggestion = "";
    protected $source;
    protected $target;
    protected $source_lang;
    protected $target_lang;
    protected $id_job;


    public function __construct() {

        parent::__construct();

        $filterArgs = array(
            'id_job'             => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'password'           => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'source'             => array( 'filter' => FILTER_UNSAFE_RAW ),
            'target'             => array( 'filter' => FILTER_UNSAFE_RAW ),
            'suggestion'         => array( 'filter' => FILTER_UNSAFE_RAW ),
            'source_lang'        => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'target_lang'        => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
        );

        $this->__postInput = filter_input_array( INPUT_POST, $filterArgs );

        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI Test scripts
        //$this->__postInput = filter_var_array( $_POST, $filterArgs );

        $this->id_job              = $this->__postInput[ 'id_job' ];
        $this->password            = $this->__postInput[ 'password' ];
        $this->source              = $this->__postInput[ 'source' ];
        $this->target              = $this->__postInput[ 'target' ];
        $this->source_lang         = $this->__postInput[ 'source_lang' ];
        $this->target_lang         = $this->__postInput[ 'target_lang' ];
        $this->suggestion          = $this->__postInput[ 'suggestion' ];

    }

    public function doAction() {

        if ( is_null( $this->source ) || $this->source === '' ) {
            $this->result[ 'errors' ][ ] = array( "code" => -1, "message" => "missing source segment" );
        }

        if ( is_null( $this->target ) || $this->target === '' ) {
            $this->result[ 'errors' ][ ] = array( "code" => -2, "message" => "missing target segment" );
        }

        if ( empty( $this->source_lang ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -3, "message" => "missing source lang" );
        }

        if ( empty( $this->target_lang ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -2, "message" => "missing target lang" );
        }

        if ( empty( $this->id_job ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -4, "message" => "id_job not valid" );

            $msg = "\n\n Critical. Quit. \n\n " . var_export( array_merge( $this->result, $_POST ), true );
            Log::doLog( $msg );
            Utils::sendErrMailReport( $msg );

            // critical. Quit.
            return -1;
        }

        //get Job Infos, we need only a row of jobs ( split )
        $job_data = getJobData( (int) $this->id_job, $this->password );

        $pCheck = new AjaxPasswordCheck();
        //check for Password correctness
        if ( empty( $job_data ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -101, "message" => "error fetching job data" );
        }

        if ( !$pCheck->grantJobAccessByJobData( $job_data, $this->password ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -10, "message" => "wrong password" );
        }

        if ( !empty( $this->result[ 'errors' ] ) ) {
            $msg = "\n\n Error \n\n " . var_export( array_merge( $this->result, $_POST ), true );
            Log::doLog( $msg );
            Utils::sendErrMailReport( $msg );

            return -1;
        }

        $config = array();

        $config[ 's' ] = CatUtils::view2rawxliff( $this->source );
        $config[ 't' ] = CatUtils::view2rawxliff( $this->target );
        $config[ 'suggestion' ] = CatUtils::view2rawxliff( $this->suggestion );
//        $config[ 'i' ] = 1;
        /*if ( $id_tms != 0 ) {

            $tms = Engine::getInstance( 1 );

            /**
             * @var $tms Engines_MyMemory
             */
//            $config = $tms->getConfigStruct();

//            $config[ 's' ]     = CatUtils::view2rawxliff( $this->source );
//            $config[ 't' ] = CatUtils::view2rawxliff( $this->target );
//            $config[ 'source' ]      = $this->source_lang;
//            $config[ 'target' ]      = $this->target_lang;
//            $config[ 'email' ]       = INIT::$MYMEMORY_API_KEY;


            $return = $this->getTagProjection($config);
        /*} else {
            $this->result[ 'code' ] = 1;
            $this->result[ 'data' ] = "NOTAGPROJ_OK";
        }*/
    }


    public function getTagProjection( $parameters ) {

        $url  = "http://52.72.102.16:8045/tags-projection?";
        $url .= http_build_query( $parameters );

//        $isSSL = stripos( $parsed_url[ 'scheme' ], "https" ) !== false;

        $ch = curl_init();

        // set URL and other appropriate options
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt( $ch, CURLOPT_HEADER, 0 );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );

        // grab URL and pass it to the browser
        $response = curl_exec( $ch );
        $response = json_decode($response);
        $translation = CatUtils::rawxliff2view( $response->{'translation'} );
        //$this->result     = array("errors" => array( array( "code" => -1000, "message" => "Ciccio" ) ), "data" => array() );
        $this->result[ 'data' ]['translation'] = $translation;



    }

}


