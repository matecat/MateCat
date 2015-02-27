<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 23/02/15
 * Time: 18.40
 */
class engineController extends ajaxController {

    private $exec;
    private $provider;
    private $id;
    private $name;
    private $clientID;
    private $clientSecret;
    private static $allowed_actions = array(
            'add', 'delete'
    );

    public function __construct() {

        //Session Enabled
        $this->checkLogin();
        //Session Disabled

        $filterArgs = array(
                'exec'      => array(
                        'filter'  => FILTER_SANITIZE_STRING,
                        'options' => array( FILTER_FLAG_STRIP_HIGH, FILTER_FLAG_STRIP_LOW )
                ),
                'id' => array(
                        'filter'  => FILTER_SANITIZE_NUMBER_INT
                ),
                'name'      => array(
                        'filter'  => FILTER_SANITIZE_STRING,
                        'options' => array( FILTER_FLAG_STRIP_HIGH, FILTER_FLAG_STRIP_LOW )
                ),
                'client_id' => array(
                        'filter'  => FILTER_SANITIZE_STRING,
                        'options' => array( FILTER_FLAG_STRIP_HIGH, FILTER_FLAG_STRIP_LOW )
                ),
                'secret'    => array(
                        'filter'  => FILTER_SANITIZE_STRING,
                        'options' => array( FILTER_FLAG_STRIP_HIGH, FILTER_FLAG_STRIP_LOW )
                ),
                'provider'  => array(
                        'filter'  => FILTER_SANITIZE_STRING,
                        'options' => array( FILTER_FLAG_STRIP_HIGH, FILTER_FLAG_STRIP_LOW )
                )
        );

        $postInput = filter_input_array( INPUT_POST, $filterArgs );

        $this->exec         = $postInput[ 'exec' ];
        $this->id           = $postInput[ 'id' ];
        $this->clientID     = $postInput[ 'client_id' ];
        $this->clientSecret = $postInput[ 'secret' ];
        $this->name         = $postInput[ 'name' ];
        $this->provider     = $postInput[ 'provider' ];

        if ( is_null( $this->exec ) || empty( $this->clientID ) ) {
            $this->result[ 'errors' ][ ] = array( 'code' => -1, 'message' => "Exec field required" );

        }

        else if ( !in_array( $this->exec, self::$allowed_actions ) ) {
            $this->result[ 'errors' ][ ] = array( 'code' => -2, 'message' => "Exec value not allowed" );
        }

        //ONLY LOGGED USERS CAN PERFORM ACTIONS ON KEYS
        if ( !$this->userIsLogged ) {
            $this->result[ 'errors' ][ ] = array(
                    'code'    => -3,
                    'message' => "Login is required to perform this action"
            );
        }
    }

    /**
     * When Called it perform the controller action to retrieve/manipulate data
     *
     * @return mixed
     */
    public function doAction() {
        if ( count( $this->result[ 'errors' ] ) > 0 ) {
            return;
        }

        switch ( $this->exec ) {
            case 'add':
                $this->add();
                break;
            case 'delete':
                $this->delete();
                break;
            default:
                break;
        }

    }

    /**
     * This method adds an engine in a user's keyring
     */
    private function add() {

        $newEngine = Engine_EngineStruct::getStruct();

        $newEngine->type = "MT";
        $newEngine->name = $this->provider;

        $validEngine = true;

        switch ( $this->provider ) {
            case strtolower( Constants_Engines::MICROSOFT_HUB ):
                $newEngine->description      = $this->name;
                $newEngine->class_load       = Constants_Engines::MICROSOFT_HUB;
                $newEngine->others           = array(
                        'client_id'     => $this->clientID,
                        'client_secret' => $this->clientSecret
                );
                break;
            default:
                $validEngine = false;
        }

        if( !$validEngine ){
            $this->result[ 'errors' ][ ] = array( 'code' => -4, 'message' => "Engine not allowed" );
            return;
        }

        //TODO: retrieve base_url from an internal source
        $newEngine->base_url = "http://www.example.com";
        $newEngine->active = 1;
        $newEngine->uid = $this->uid;

        $engineDAO = new Engine_EngineDAO( Database::obtain() );
        $result = $engineDAO->create( $newEngine );

        if(! $result instanceof Engine_EngineStruct){
            $this->result[ 'errors' ][ ] = array( 'code' => -9, 'message' => "Creation failed. Generic error" );
        }

    }

    /**
     * This method deletes an engine from a user's keyring
     */
    private function delete(){
        if(empty($this->id)){
            $this->result['errors'][] = array( 'code' => -5, 'message' => "Engine id required" );
            return;
        }

        $engineToBeDeleted = Engine_EngineStruct::getStruct();
        $engineToBeDeleted->id = $this->id;
        $engineToBeDeleted->uid = $this->uid;


        $engineDAO = new Engine_EngineDAO( Database::obtain() );
        $result = $engineDAO->delete( $engineToBeDeleted );

        if(! $result instanceof Engine_EngineStruct){
            $this->result[ 'errors' ][ ] = array( 'code' => -9, 'message' => "Deletion failed. Generic error" );
        }
    }
} 