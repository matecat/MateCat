<?php

use Matecat\SubFiltering\MateCatFilter;

class getTagProjectionController extends ajaxController {

    protected $__postInput = [];

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

        $filterArgs = [
                'id_segment'  => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'id_job'      => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'password'    => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'source'      => [ 'filter' => FILTER_UNSAFE_RAW ],
                'target'      => [ 'filter' => FILTER_UNSAFE_RAW ],
                'suggestion'  => [ 'filter' => FILTER_UNSAFE_RAW ],
                'source_lang' => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'target_lang' => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
        ];

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
        $this->id_segment  = $this->__postInput[ 'id_segment' ];

        \Log::$fileName = 'tagProjection.log';

    }

    public function doAction() {

        if ( is_null( $this->source ) || $this->source === '' ) {
            $this->result[ 'errors' ][] = [ "code" => -1, "message" => "missing source segment" ];
        }

        if ( is_null( $this->target ) || $this->target === '' ) {
            $this->result[ 'errors' ][] = [ "code" => -2, "message" => "missing target segment" ];
        }

        if ( empty( $this->source_lang ) ) {
            $this->result[ 'errors' ][] = [ "code" => -3, "message" => "missing source lang" ];
        }

        if ( empty( $this->target_lang ) ) {
            $this->result[ 'errors' ][] = [ "code" => -2, "message" => "missing target lang" ];
        }

        if ( empty( $this->id_job ) ) {
            $this->result[ 'errors' ][] = [ "code" => -4, "message" => "id_job not valid" ];

            $msg = "\n\n Critical. Quit. \n\n " . var_export( array_merge( $this->result, $_POST ), true );
            Log::doJsonLog( $msg );
            Utils::sendErrMailReport( $msg );

            // critical. Quit.
            return -1;
        }

        //check Job password
        $jobStruct = Chunks_ChunkDao::getByIdAndPassword( $this->id_job, $this->password );
        $this->featureSet->loadForProject( $jobStruct->getProject() );

        $this->getTagProjection();

    }

    public function getTagProjection() {

        /**
         * @var $engine Engines_MyMemory
         */
        $engine = Engine::getInstance( 1 );
        $engine->setFeatureSet( $this->featureSet );

        $dataRefMap = Segments_SegmentOriginalDataDao::getSegmentDataRefMap( $this->id_segment );
        $Filter     = MateCatFilter::getInstance( $this->getFeatureSet(), $this->source_lang, $this->target_lang, $dataRefMap );

        $config                  = [];
        $config[ 'dataRefMap' ]  = $dataRefMap;
        $config[ 'source' ]      = $Filter->fromLayer2ToLayer1( $this->source );
        $config[ 'target' ]      = $Filter->fromLayer2ToLayer1( $this->target );
        $config[ 'source_lang' ] = $this->source_lang;
        $config[ 'target_lang' ] = $this->target_lang;
        $config[ 'suggestion' ]  = $Filter->fromLayer2ToLayer1( $this->suggestion );

        $result = $engine->getTagProjection( $config );
        if ( empty( $result->error ) ) {
            $this->result[ 'data' ][ 'translation' ] = $Filter->fromLayer1ToLayer2( $result->responseData );
            $this->result[ 'code' ]                  = 0;
        } else {
            $this->result[ 'code' ]   = $result->error->code;
            $this->result[ 'errors' ] = $result->error;
            $this->logTagProjection(
                    [
                            'request' => $config,
                            'error'   => $result->error
                    ]
            );
        }

        \Log::$fileName = $this->old_logFile;
    }

    public function logTagProjection( $msg = null ) {

        if ( !$msg ) {
            \Log::doJsonLog( $this->result[ 'data' ] );
        } else {
            \Log::doJsonLog( $msg );
        }

    }
}


