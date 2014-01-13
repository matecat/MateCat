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
    private $automatic;

    public function __construct() {

        $this->disableSessions();
        parent::__construct();

        $filterArgs = array(
            'exec'        => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'id_job'      => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'password'    => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'segment'     => array( 'filter' => FILTER_UNSAFE_RAW ),
            'translation' => array( 'filter' => FILTER_UNSAFE_RAW ),
            'comment'     => array( 'filter' => FILTER_UNSAFE_RAW ),
            'automatic'   => array( 'filter' => FILTER_VALIDATE_BOOLEAN ),
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
        $this->automatic   = $__postInput[ 'automatic' ];

    }

    public function doAction() {

        $st = getJobData($this->id_job, $this->password);

        try {

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

                    /**
                     * Return only exact matches in glossary when a search is executed over the entire segment
                     *
                     * Example:
                     * Segment: On average, Members of the House of Commons have 4,2 support staff.
                     *
                     * Glossary terms found: House of Commons, House of Lords
                     *
                     * Return: House of Commons
                     *
                     */
                    if( $this->automatic ){
                        foreach( $TMS_RESULT as $k => $val ){
                            if( mb_stripos( $this->segment, $k ) === false ){
                                unset( $TMS_RESULT[$k] );
                            }
                        }
                    }
                    $this->result['data']['matches'] = $TMS_RESULT;

                    break;
                case 'set':
                    if ( empty($st['id_translator']) ) {
                        $newUser = TMS::createMyMemoryKey( $this->id_job ); //throws exception
                        $config[ 'id_user' ]     = $newUser->id;
                    }
                    $TMS_RESULT = $_TMS->set($config);
                    $set_code = $TMS_RESULT;

                    if ( $set_code ) {
//                       Often the get method after a set is not in real time, so return the same values ( FAKE )
//                        $TMS_GET_RESULT = $_TMS->get($config)->get_glossary_matches_as_array();
//                        $this->result['data']['matches'] = $TMS_GET_RESULT;
                        $this->result['data']['matches'] = array(
                            $config['segment'] => array(
                                    array(
                                        'segment'          => $config['segment'],
                                        'translation'      => $config['segment'],
                                        'last_update_date' => date_create()->format('Y-m-d H:i:m'),
                                        'last_updated_by'  => $newUser->id,
                                        'created_by'       => $newUser->id,
                                        'target_note'      => $config['tnote'],
                                    )
                            )
                        );
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