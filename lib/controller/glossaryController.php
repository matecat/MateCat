<?php
/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 06/12/13
 * Time: 15.55
 *
 */
include_once INIT::$UTILS_ROOT . '/engines/engine.class.php';
include_once INIT::$UTILS_ROOT . "/engines/mt.class.php";
include_once INIT::$UTILS_ROOT . "/engines/tms.class.php";
include_once INIT::$MODEL_ROOT . "/queries.php";

class glossaryController extends ajaxcontroller {

    private $exec;
    private $segment;
    private $translation;
    private $source_lang;
    private $target_lang;
    private $id_translator;

    public function __construct() {
        parent::__construct();

        $filterArgs = array(
            'exec'          => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'segment'       => array( 'filter' => FILTER_UNSAFE_RAW ),
            'translation'   => array( 'filter' => FILTER_UNSAFE_RAW ),
            'source_lang'   => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'target_lang'   => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'id_translator' => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
        );

        //$__postInput = filter_input_array( INPUT_POST, $filterArgs );

        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI
        $__postInput = filter_var_array( $_POST, $filterArgs );

        $this->exec          = $__postInput[ 'exec' ];
        $this->segment       = $__postInput[ 'segment' ];
        $this->translation   = $__postInput[ 'translation' ];
        $this->source_lang   = $__postInput[ 'source_lang' ];
        $this->target_lang   = $__postInput[ 'target_lang' ];
        $this->id_translator = $__postInput[ 'id_translator' ];

    }

    public function doAction() {



        try {

            if ( empty( $this->id_translator ) ) {
                throw new Exception( "No Private Glossary Key provided.", -1 );
            }

            $config = TMS::getConfigStruct();

            $config[ 'segment' ]       = $this->segment;
            $config[ 'translation' ]   = $this->translation;
            $config[ 'source_lang' ]   = $this->source_lang;
            $config[ 'target_lang' ]   = $this->target_lang;
            $config[ 'email' ]         = "demo@matecat.com";
            $config[ 'id_user' ]       = $this->id_translator;
            $config[ 'isGlossary' ]    = true;

            /**
             * For future reminder
             *
             * MyMemory should not be the only Glossary provider
             *
             */
            $_TMS = new TMS( 1 /* MyMemory */ );

            switch ( $this->exec ) {

                case 'get':
                    $TMS_RESULT = $_TMS->get( $config )->get_matches_as_array();
                    $this->result[ 'data' ][ 'matches' ] = $TMS_RESULT;
                    break;
                case 'set':
                    $TMS_RESULT = $_TMS->set( $config );
                    $this->result[ 'code' ] = $TMS_RESULT;
                    $this->result[ 'data' ] = ( $TMS_RESULT ? 'OK' : null );
                    break;
                case 'delete':
                    $TMS_RESULT = $_TMS->delete( $config );
                    $this->result[ 'code' ] = $TMS_RESULT;
                    $this->result[ 'data' ] = ( $TMS_RESULT ? 'OK' : null );
                    break;

            }

        } catch ( Exception $e ) {
            $this->result[ 'errors' ][ ] = array( "code" => $e->getCode(), "message" => $e->getMessage() );
        }

    }

}