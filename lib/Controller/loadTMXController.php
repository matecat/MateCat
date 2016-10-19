<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 14/10/14
 * Time: 16.04
 * 
 */

class loadTMXController extends ajaxController {


    /**
     * @var string The name of the uploaded TMX
     */
    private $name;

    /**
     * @var string The key to be associated to the tmx
     */
    private $tm_key;

    /**
     * @var stdClass
     */
    private $file;

    /**
     * @var string
     */
    private $exec;

    private static $acceptedActions = array( "newTM", "uploadStatus" );

    protected $TMService;

    public function __construct() {

        parent::__construct();
        parent::checkLogin();

        $filterArgs = array(
                'name'   => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW
                ),
                'tm_key' => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'exec'   => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                )
        );

        $postInput = (object)filter_input_array( INPUT_POST, $filterArgs );

        $this->name     = $postInput->name;
        $this->tm_key   = $postInput->tm_key;
        $this->exec     = $postInput->exec;

        if ( !isset( $this->tm_key ) || is_null( $this->tm_key ) || empty( $this->tm_key ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -2, "message" => "Please specify a TM key." );
        }

        if ( empty( $this->exec ) || !in_array( $this->exec, self::$acceptedActions ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -7, "message" => "Action not valid." );
        }

    }

    /**
     * When Called it perform the controller action to retrieve/manipulate data
     *
     * @return mixed
     */
    public function doAction() {

        //check if there was an error in constructor. If so, stop execution.
        if ( !empty( $this->result[ 'errors' ] ) ) {
            $this->result[ 'success' ] = false;
            return false;
        }

        $this->result[ 'errors' ]  = array();

        $this->TMService = new TMSService();
        $this->TMService->setTmKey( $this->tm_key );

        try {

            if ( $this->exec == "newTM" ) {

                $this->file = $this->TMService->uploadFile();

                foreach( $this->file as $fileInfo ){
                    if ( FilesStorage::pathinfo_fix( strtolower( $fileInfo->name ), PATHINFO_EXTENSION ) !== 'tmx' ) {
                        throw new Exception( "Please upload a TMX.", -8 );
                    }

                    $this->TMService->setName( $fileInfo->name );
                    $this->TMService->addTmxInMyMemory();

                    /*
                     * Update a memory key with the name of th TMX if the key name is empty
                     */
                    $mkDao                   = new TmKeyManagement_MemoryKeyDao( Database::obtain() );
                    $searchMemoryKey         = new TmKeyManagement_MemoryKeyStruct();
                    $key                     = new TmKeyManagement_TmKeyStruct();
                    $key->key                = $this->tm_key;

                    $searchMemoryKey->uid    = $this->uid;
                    $searchMemoryKey->tm_key = $key;
                    $userMemoryKey           = $mkDao->read( $searchMemoryKey );

                    if ( empty( $userMemoryKey[0]->tm_key->name ) ) {
                        $userMemoryKey[0]->tm_key->name = $fileInfo->name;
                        $mkDao->updateList( $userMemoryKey );
                    }

                }


            } else {

                $this->TMService->setName( $this->name );
                $status                      = $this->TMService->tmxUploadStatus();
                $this->result[ 'data' ]      = $status[ 'data' ];
                $this->result[ 'completed' ] = $status[ 'completed' ];

            }

            $this->result[ 'success' ] = true;

        } catch ( Exception $e ) {
            $this->result[ 'success' ]   = false;
            $this->result[ 'errors' ][ ] = array( "code" => $e->getCode(), "message" => $e->getMessage() );
        }

    }

} 