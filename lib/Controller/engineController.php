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
    private $engineData;
    private static $allowed_actions = array(
            'add', 'delete'
    );

    public function __construct() {

        parent::__construct();

        //Session Enabled
        $this->checkLogin();
        //Session Disabled

        $filterArgs = array(
                'exec'      => array(
                        'filter'  => FILTER_SANITIZE_STRING,
                        'flags'   => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW
                ),
                'id' => array(
                        'filter'  => FILTER_SANITIZE_NUMBER_INT
                ),
                'name'      => array(
                        'filter'  => FILTER_SANITIZE_STRING,
                        'flags'   => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW
                ),
                'data'    => array(
                        'filter'  => FILTER_SANITIZE_STRING,
                        'flags'   => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW | FILTER_FLAG_NO_ENCODE_QUOTES
                ),
                'provider'  => array(
                        'filter'  => FILTER_SANITIZE_STRING,
                        'flags'   => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW
                )
        );

        $postInput = filter_input_array( INPUT_POST, $filterArgs );

        $this->exec         = $postInput[ 'exec' ];
        $this->id           = $postInput[ 'id' ];
        $this->name         = $postInput[ 'name' ];
        $this->provider     = $postInput[ 'provider' ];
        $this->engineData   = json_decode( $postInput[ 'data' ], true );

        if ( is_null( $this->exec ) ) {
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
                $this->disable();
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

        switch ( strtolower( $this->provider ) ) {
            case strtolower( Constants_Engines::MICROSOFT_HUB ):

                /**
                 * Create a record of type MicrosoftHub
                 */
                $newEngine = EnginesModel_MicrosoftHubStruct::getStruct();

                $newEngine->name                                = $this->name;
                $newEngine->uid                                 = $this->uid;
                $newEngine->type                                = Constants_Engines::MT;
                $newEngine->extra_parameters[ 'client_id' ]     = $this->engineData['client_id'];
                $newEngine->extra_parameters[ 'client_secret' ] = $this->engineData['secret'];

                break;
            case strtolower( Constants_Engines::MOSES ):

                /**
                 * Create a record of type Moses
                 */
                $newEngine = EnginesModel_MosesStruct::getStruct();

                $newEngine->name                                = $this->name;
                $newEngine->uid                                 = $this->uid;
                $newEngine->type                                = Constants_Engines::MT;
                $newEngine->base_url                            = $this->engineData[ 'url' ];
                $newEngine->extra_parameters[ 'client_secret' ] = $this->engineData[ 'secret' ];

                break;

            case strtolower( Constants_Engines::IP_TRANSLATOR ):

                /**
                 * Create a record of type IPTranslator
                 */
                $newEngine = EnginesModel_IPTranslatorStruct::getStruct();

                $newEngine->name                                = $this->name;
                $newEngine->uid                                 = $this->uid;
                $newEngine->type                                = Constants_Engines::MT;
                $newEngine->extra_parameters[ 'client_secret' ] = $this->engineData[ 'secret' ];

                break;

            case strtolower( Constants_Engines::DEEPLINGO ):

                /**
                 * Create a record of type IPTranslator
                 */
                $newEngine = EnginesModel_DeepLingoStruct::getStruct();

                $newEngine->name                                = $this->name;
                $newEngine->uid                                 = $this->uid;
                $newEngine->type                                = Constants_Engines::MT;
                $newEngine->base_url                            = $this->engineData[ 'url' ];
                $newEngine->extra_parameters[ 'client_secret' ] = $this->engineData[ 'secret' ];

                break;
            case strtolower( Constants_Engines::APERTIUM ):                            

                /**
                 * Create a record of type APERTIUM
                 */
                $newEngine = EnginesModel_ApertiumStruct::getStruct();

                $newEngine->name                                = $this->name;
                $newEngine->uid                                 = $this->uid;
                $newEngine->type                                = Constants_Engines::MT;                
                $newEngine->extra_parameters[ 'client_secret' ] = $this->engineData[ 'secret' ];
                
                break;                

            case strtolower( Constants_Engines::ALTLANG ):                            

                /**
                 * Create a record of type ALTLANG
                 */
                $newEngine = EnginesModel_AltlangStruct::getStruct();

                $newEngine->name                                = $this->name;
                $newEngine->uid                                 = $this->uid;
                $newEngine->type                                = Constants_Engines::MT;                
                $newEngine->extra_parameters[ 'client_secret' ] = $this->engineData[ 'secret' ];
                
                break;                                
                
            default:
                $validEngine = false;
        }

        if( !$validEngine ){
            $this->result[ 'errors' ][ ] = array( 'code' => -4, 'message' => "Engine not allowed" );
            return;
        }

        $engineDAO = new EnginesModel_EngineDAO( Database::obtain() );
        $result = $engineDAO->create( $newEngine );

        if(! $result instanceof EnginesModel_EngineStruct){
            $this->result[ 'errors' ][ ] = array( 'code' => -9, 'message' => "Creation failed. Generic error" );
            return;
        }

        if( $newEngine instanceof EnginesModel_MicrosoftHubStruct ){

            $engine_test = Engine::getInstance( $result->id );
            $config = $engine_test->getConfigStruct();
            $config[ 'segment' ] = "Hello World";
            $config[ 'source' ]  = "en-US";
            $config[ 'target' ]  = "fr-FR";

            $mt_result = $engine_test->get( $config );

            if ( isset( $mt_result['error']['code'] ) ) {
                $this->result[ 'errors' ][ ] = $mt_result['error'];
                $engineDAO->delete( $result );
                return;
            }

        } elseif ( $newEngine instanceof EnginesModel_IPTranslatorStruct ){

            $engine_test = Engine::getInstance( $result->id );

            /**
             * @var $engine_test Engines_IPTranslator
             */
            $config = $engine_test->getConfigStruct();
            $config[ 'source' ]  = "en-US";
            $config[ 'target' ]  = "fr-FR";

            $mt_result = $engine_test->ping( $config );

            if ( isset( $mt_result['error']['code'] ) ) {
                $this->result[ 'errors' ][ ] = $mt_result['error'];
                $engineDAO->delete( $result );
                return;
            }

        }

        $this->result['data']['id'] = $result->id;

    }

    /**
     * This method deletes an engine from a user's keyring
     */
    private function disable(){

        if ( empty( $this->id ) ) {
            $this->result[ 'errors' ][ ] = array( 'code' => -5, 'message' => "Engine id required" );
            return;
        }

        $engineToBeDeleted = EnginesModel_EngineStruct::getStruct();
        $engineToBeDeleted->id = $this->id;
        $engineToBeDeleted->uid = $this->uid;

        $engineDAO = new EnginesModel_EngineDAO( Database::obtain() );
        $result = $engineDAO->disable( $engineToBeDeleted );

        if(! $result instanceof EnginesModel_EngineStruct){
            $this->result[ 'errors' ][ ] = array( 'code' => -9, 'message' => "Deletion failed. Generic error" );
            return;
        }

        $this->result['data']['id'] = $result->id;

    }

} 