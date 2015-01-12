<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 15/10/14
 * Time: 15.44
 */

include INIT::$MODEL_ROOT . "/queries.php";

class userKeysController extends ajaxController {

    private $key;

    private $description;

    private $exec;

    public function __construct() {

        //Session Enabled
        $this->checkLogin();
        //Session Disabled

        //define input filters
        $filterArgs = array(
                'exec'        => array(
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => array( FILTER_FLAG_STRIP_LOW, FILTER_FLAG_STRIP_HIGH )
                ),
                'key'         => array(
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => array( FILTER_FLAG_STRIP_LOW, FILTER_FLAG_STRIP_HIGH )
                ),
                'description' => array(
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => array( FILTER_FLAG_STRIP_LOW, FILTER_FLAG_STRIP_HIGH )
                ),
        );

        //filter input
        $_postInput = filter_input_array( INPUT_POST, $filterArgs );

        //assign variables
        $this->exec        = $_postInput[ 'exec' ];
        $this->key         = $_postInput[ 'key' ];
        $this->description = $_postInput[ 'description' ];

        //check for eventual errors on the input passed
        $this->result[ 'errors' ] = array();
        if ( empty( $this->key ) ) {
            $this->result[ 'errors' ][ ] = array(
                    'code'    => -2,
                    'message' => "Key missing"
            );
        }

        if ( array_search( $this->exec, array( 'update', 'newKey' ) ) === false ) {
            $this->result[ 'errors' ][ ] = array(
                    'code'    => -5,
                    'message' => "No method $this->exec allowed."
            );
        }

    }

    /**
     * When Called it perform the controller action to retrieve/manipulate data
     *
     * @return void
     */
    function doAction() {


        //raise an error only if user is not logged and if he want update a key
        if ( !$this->userIsLogged && $this->exec == 'update' ) {
            $this->result[ 'errors' ][ ] = array(
                    'code'    => -1,
                    'message' => "Login is required to perform this action"
            );
        } elseif( !$this->userIsLogged && $this->exec == 'new Key' ) {
            //if the user is not logged
            //ONLY LOGGED USERS CAN ADD KEYS TO THEIR KEYRING
            return;
        }

        //if some error occured, stop execution.
        if ( count( @$this->result[ 'errors' ] ) ) {
            return;
        }

        try {

            $tmService = new TMSService();
            $tmService->setTmKey( $this->key );
            $tmService->checkCorrectKey();

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
            $this->result[ 'data' ]      = 'KO';
            $this->result[ 'errors' ][ ] = array( "code" => $e->getCode(), "message" => $e->getMessage() );
        }

    }

} 