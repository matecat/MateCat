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

        $this->result[ 'code' ]       = 0;
        $this->result[ 'data' ]       = "KO";

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

        $newEngine = null;
        $validEngine = true;

        switch ( $this->provider ) {
            case strtolower( Constants_Engines::MICROSOFT_HUB ):

                /**
                 * Create a record of type MicrosoftHub
                 */
                $newEngine = Engine_MicrosoftHubStruct::getStruct();

                $newEngine->name                                = $this->name;
                $newEngine->uid                                 = $this->uid;
                $newEngine->extra_parameters[ 'client_id' ]     = $this->clientID;
                $newEngine->extra_parameters[ 'client_secret' ] = $this->clientSecret;

                break;
            default:
                $validEngine = false;
        }

        if( !$validEngine ){
            $this->result[ 'errors' ][ ] = array( 'code' => -4, 'message' => "Engine not allowed" );
            return;
        }

        $engineDAO = new Engine_EngineDAO( Database::obtain() );
        $result = $engineDAO->create( $newEngine );

        if(! $result instanceof Engine_EngineStruct){
            $this->result[ 'errors' ][ ] = array( 'code' => -9, 'message' => "Creation failed. Generic error" );
            return;
        }

        $this->result[ 'code' ]       = 1;
        $this->result[ 'data' ]       = "OK";

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