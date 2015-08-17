<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 15/10/14
 * Time: 15.44
 */
class userKeysController extends ajaxController {

    private $key;

    private $description;

    private $exec;

    private static $allowed_exec = array(
            'delete', 'update', 'newKey'
    );

    public function __construct() {

        parent::__construct();

        //Session Enabled
        $this->checkLogin();
        //Session Disabled

        //define input filters
        $filterArgs = array(
                'exec'        => array(
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'key'         => array(
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'description' => array(
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
        );

        //filter input
        $_postInput = filter_input_array( INPUT_POST, $filterArgs );

        //assign variables
        $this->exec        = $_postInput[ 'exec' ];
        $this->key         = trim( $_postInput[ 'key' ] );
        $this->description = $_postInput[ 'description' ];

        //check for eventual errors on the input passed
        $this->result[ 'errors' ] = array();
        if ( empty( $this->key ) ) {
            $this->result[ 'errors' ][] = array(
                    'code'    => -2,
                    'message' => "Key missing"
            );
        }

        if ( array_search( $this->exec, self::$allowed_exec ) === false ) {
            $this->result[ 'errors' ][] = array(
                    'code'    => -5,
                    'message' => "No method $this->exec allowed."
            );
        }

        //ONLY LOGGED USERS CAN PERFORM ACTIONS ON KEYS
        if ( !$this->userIsLogged ) {
            $this->result[ 'errors' ][] = array(
                    'code'    => -1,
                    'message' => "Login is required to perform this action"
            );
        }

    }

    /**
     * When Called it perform the controller action to retrieve/manipulate data
     *
     * @return void
     */
    function doAction() {
        //if some error occured, stop execution.
        if ( count( @$this->result[ 'errors' ] ) ) {
            return;
        }

        try {
            $tmService = new TMSService();
            $tmService->setTmKey( $this->key );

            //validate the key
            try {
                $keyExists = $tmService->checkCorrectKey();
            } catch ( Exception $e ) {
                /* PROVIDED KEY IS NOT VALID OR WRONG, $keyExists IS NOT SET */
                Log::doLog( $e->getMessage() );
            }

            if ( !isset( $keyExists ) || $keyExists === false ) {
                Log::doLog( __METHOD__ . " -> TM key is not valid." );
                throw new Exception( "TM key is not valid.", -4 );
            }

            $tmKeyStruct       = new TmKeyManagement_TmKeyStruct();
            $tmKeyStruct->key  = $this->key;
            $tmKeyStruct->name = $this->description;
            $tmKeyStruct->tm   = true;
            $tmKeyStruct->glos = true;


            $mkDao = new TmKeyManagement_MemoryKeyDao( Database::obtain() );

            $memoryKeyToUpdate         = new TmKeyManagement_MemoryKeyStruct();
            $memoryKeyToUpdate->uid    = $this->uid;
            $memoryKeyToUpdate->tm_key = $tmKeyStruct;

            switch ( $this->exec ) {
                case 'delete':
                    $userMemoryKeys = $mkDao->disable( $memoryKeyToUpdate );
                    break;
                case 'update':
                    $userMemoryKeys = $mkDao->update( $memoryKeyToUpdate );
                    break;
                case 'newKey':
                    $userMemoryKeys = $mkDao->create( $memoryKeyToUpdate );
                    break;
                default:
                    throw new Exception( "Unexpected Exception", -4 );
            }

            if ( !$userMemoryKeys ) {
                throw new Exception( "This key wasn't found in your keyring.", -3 );
            }


        } catch ( Exception $e ) {
            $this->result[ 'data' ]     = 'KO';
            $this->result[ 'errors' ][] = array( "code" => $e->getCode(), "message" => $e->getMessage() );
        }

    }

} 