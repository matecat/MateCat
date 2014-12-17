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

    public function __construct() {

        //Session Enabled
        $this->checkLogin();
        //Session Disabled

        if( !$this->userIsLogged ){
            $this->result[ 'errors' ][ ] = array(
                    'code'    => -1,
                    'message' => "Login is required to perform this action"
            );
            return;
        }

        //define input filters
        $filterArgs = array(
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

    }


    /**
     * When Called it perform the controller action to retrieve/manipulate data
     *
     * @return mixed
     */
    function doAction() {

        //if some error occured, stop execution.
        if ( count( @$this->result[ 'errors' ] ) ) {
            return false;
        }

        try {

            $tmKeyStruct              = new TmKeyManagement_TmKeyStruct();
            $tmKeyStruct->key         = $this->key;
            $tmKeyStruct->name        = $this->description;
            $tmKeyStruct->tm          = true;
            $tmKeyStruct->glos        = true;


            $mkDao = new TmKeyManagement_MemoryKeyDao( Database::obtain() );

            $memoryKeyToUpdate         = new TmKeyManagement_MemoryKeyStruct();
            $memoryKeyToUpdate->uid    = $this->uid;
            $memoryKeyToUpdate->tm_key = $tmKeyStruct;

            $userMemoryKeys = $mkDao->update( $memoryKeyToUpdate );
            if ( !$userMemoryKeys ){
                throw new Exception( "This key wasn't found in your keyring.", -3 );
            }

        } catch ( Exception $e ){
            $this->result[ 'data' ]      = 'KO';
            $this->result[ 'errors' ][ ] = array( "code" => $e->getCode(), "message" => $e->getMessage() );
        }

    }

} 