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
    private $name;
    private $clientID;
    private $clientSecret;
    private static $allowed_actions = array(
            'add'
    );

    public function __construct() {

        $filterArgs = array(
                'exec'      => array(
                        'filter'  => FILTER_SANITIZE_STRING,
                        'options' => array( FILTER_FLAG_STRIP_HIGH, FILTER_FLAG_STRIP_LOW )
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

            default:
                break;
        }

    }

    private function add() {
        $newEngine = Engine_EngineStruct::getStruct();

        $newEngine->type = "MT";
        $newEngine->name = $this->provider;

        $validEngine = true;

        switch ( $this->provider ) {
            case 'microsofthub':
                $newEngine->description = $this->name;
                $newEngine->others = array(
                        'client_id'     => $this->clientID,
                        'client_secret' => $this->clientSecret
                );
                break;
            default:
                $validEngine = false;
        }

        if( !$validEngine ){
            $this->result[ 'errors' ][ ] = array( 'code' => -3, 'message' => "Engine not allowed" );
            return;
        }

        //TODO: retrieve base_url from an internal source
        $newEngine->base_url = "http://www.example.com";
        $newEngine->active = 1;

        $engineDAO = new Engine_EngineDAO( Database::obtain() );
        $engineDAO->create( $newEngine );

    }
} 