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
    private $id_job;
    private $password;
    private $segment;
    private $translation;
    private $comment;

    public function __construct() {
        parent::__construct();

        $filterArgs = array(
            'exec'        => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'id_job'      => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'password'    => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'segment'     => array( 'filter' => FILTER_UNSAFE_RAW ),
            'translation' => array( 'filter' => FILTER_UNSAFE_RAW ),
            'comment'     => array( 'filter' => FILTER_UNSAFE_RAW ),
        );

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );
        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI
        //$__postInput = filter_var_array( $_POST, $filterArgs );

        $this->exec        = $__postInput[ 'exec' ];
        $this->id_job      = $__postInput[ 'id_job' ];
        $this->password    = $__postInput[ 'password' ];
        $this->segment     = $__postInput[ 'segment' ];
        $this->translation = $__postInput[ 'translation' ];
        $this->comment     = $__postInput[ 'comment' ];

    }

    public function doAction() {

        $st = getJobData($this->id_job, $this->password);

        try {

            if (empty($st['id_translator'])) {
                throw new Exception("No Private Glossary Key provided for Job.", -1);
            }

            $config = TMS::getConfigStruct();

            $config[ 'segment' ]     = $this->segment;
            $config[ 'translation' ] = $this->translation;
            $config[ 'tnote' ]       = $this->comment;
            $config[ 'source_lang' ] = $st[ 'source' ];
            $config[ 'target_lang' ] = $st[ 'target' ];
            $config[ 'email' ]       = "demo@matecat.com";
            $config[ 'id_user' ]     = $st[ 'id_translator' ];
            $config[ 'isGlossary' ]  = true;

            /**
             * For future reminder
             *
             * MyMemory should not be the only Glossary provider
             *
             */
            $_TMS = new TMS(1 /* MyMemory */);

            switch ($this->exec) {

                case 'get':
                    $TMS_RESULT = $_TMS->get($config)->get_glossary_matches_as_array();
                    $this->result['data']['matches'] = $TMS_RESULT;
                    break;
                case 'set':
                    $TMS_RESULT = $_TMS->set($config);
                    $set_code = $TMS_RESULT;
                    if ($set_code) {
                        $TMS_GET_RESULT = $_TMS->get($config)->get_glossary_matches_as_array();
                        $this->result['data']['matches'] = $TMS_GET_RESULT;
                    }
                    break;
                case 'update':
                    $TMS_RESULT = $_TMS->update($config);
                    $set_code = $TMS_RESULT;
                    if ($set_code) {
                        $TMS_GET_RESULT = $_TMS->get($config)->get_glossary_matches_as_array();
                        $this->result['data']['matches'] = $TMS_GET_RESULT;
                    }
                    break;
                case 'delete':
                    $TMS_RESULT = $_TMS->delete($config);
                    $this->result['code'] = $TMS_RESULT;
                    $this->result['data'] = ( $TMS_RESULT ? 'OK' : null );
                    break;
            }
        } catch (Exception $e) {
            $this->result['errors'][] = array("code" => $e->getCode(), "message" => $e->getMessage());
        }
    }

}