<?php

use FilesStorage\AbstractFilesStorage;
use FilesStorage\FilesStorageFactory;

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

    private static $acceptedActions = [ "newTM", "uploadStatus" ];

    protected $TMService;

    public function __construct() {

        parent::__construct();
        parent::readLoginInfo();

        $filterArgs = [
                'name'   => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW
                ],
                'tm_key' => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'exec'   => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ]
        ];

        $postInput = (object)filter_input_array( INPUT_POST, $filterArgs );

        $this->name   = $postInput->name;
        $this->tm_key = $postInput->tm_key;
        $this->exec   = $postInput->exec;

        if ( empty( $this->tm_key ) ) {

            if ( empty( INIT::$DEFAULT_TM_KEY ) ) {
                $this->result[ 'errors' ][] = [ "code" => -2, "message" => "Please specify a TM key." ];

                return;
            }

            /*
             * Added the default Key.
             * This means if no private key are provided the TMX will be loaded in the default MyMemory key
             */
            $this->tm_key = INIT::$DEFAULT_TM_KEY;

        }

        if ( empty( $this->exec ) || !in_array( $this->exec, self::$acceptedActions ) ) {
            $this->result[ 'errors' ][] = [ "code" => -7, "message" => "Action not valid." ];
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

        $this->result[ 'errors' ] = [];

        $this->TMService = new TMSService();
        $this->TMService->setTmKey( $this->tm_key );

        try {

            if ( $this->exec == "newTM" ) {

                $this->file = $this->TMService->uploadFile();

                foreach ( $this->file as $fileInfo ) {
                    if ( AbstractFilesStorage::pathinfo_fix( strtolower( $fileInfo->name ), PATHINFO_EXTENSION ) !== 'tmx' ) {
                        throw new Exception( "Please upload a TMX.", -8 );
                    }

                    $this->TMService->setName( $fileInfo->name );
                    $this->TMService->setFile( [ $fileInfo ] );
                    $this->TMService->addTmxInMyMemory();

                    $this->featureSet->run( 'postPushTMX', $fileInfo, $this->user, $this->TMService->getTMKey() );

                    /*
                     * We update the KeyRing only if this is NOT the Default MyMemory Key
                     *
                     * If it is NOT the default the key belongs to the user, so it's correct to update the user keyring.
                     */
                    if ( $this->tm_key != INIT::$DEFAULT_TM_KEY ) {

                        /*
                         * Update a memory key with the name of th TMX if the key name is empty
                         */
                        $mkDao           = new TmKeyManagement_MemoryKeyDao( Database::obtain() );
                        $searchMemoryKey = new TmKeyManagement_MemoryKeyStruct();
                        $key             = new TmKeyManagement_TmKeyStruct();
                        $key->key        = $this->tm_key;

                        $searchMemoryKey->uid    = $this->user->uid;
                        $searchMemoryKey->tm_key = $key;
                        $userMemoryKey           = $mkDao->read( $searchMemoryKey );

                        if ( empty( $userMemoryKey[0]->tm_key->name ) && !empty( $userMemoryKey ) ) {
                            $userMemoryKey[0]->tm_key->name = $fileInfo->name;
                            $mkDao->atomicUpdate( $userMemoryKey[0] );
                        }

                    }

                }

            } else {

                $this->TMService->setName( Utils::fixFileName( $this->name ) );
                $status                      = $this->TMService->tmxUploadStatus();
                $this->result[ 'data' ]      = $status[ 'data' ];
                $this->result[ 'completed' ] = $status[ 'completed' ];

            }

            $this->result[ 'success' ] = true;

        } catch ( Exception $e ) {
            $this->result[ 'success' ]  = false;
            $this->result[ 'errors' ][] = [ "code" => $e->getCode(), "message" => $e->getMessage() ];
        }

    }

} 