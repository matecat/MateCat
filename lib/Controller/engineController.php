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
            'add', 'delete', 'execute'
    );
    private static $allowed_execute_functions = array(
        'letsmt' => array('getTermList')
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
            case 'execute':
                $this->execute();
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

            case strtolower( Constants_Engines::LETSMT ):

                /**
                 * Create a record of type LetsMT
                 */
                $newEngine = EnginesModel_LetsMTStruct::getStruct();

                $newEngine->name                                = $this->name;
                $newEngine->uid                                 = $this->uid;
                $newEngine->type                                = Constants_Engines::MT;
                $newEngine->extra_parameters[ 'client_id' ]     = $this->engineData['client_id'];
                $newEngine->extra_parameters[ 'system_id' ]     = $this->engineData[ 'system_id' ]; // whether this has been set or not indicates whether we should
                                                                                                    // return the newly added system's id or the list of available systems
                                                                                                    // for the user to choose from. the check happens later on
                $newEngine->extra_parameters[ 'terms_id' ]      = $this->engineData[ 'terms_id' ];
                $newEngine->extra_parameters[ 'use_qe' ]        = $this->engineData[ 'use_qe' ];
                if ($newEngine->extra_parameters[ 'use_qe' ]) {
                    $minQEString = $this->engineData[ 'minimum_qe' ];
                    if (!is_numeric($minQEString)) {
                        $this->result[ 'errors' ][ ] = array( 'code' => -13, 'message' => "Minimum QE score should be a number between 0 and 1." );
                        return;
                    }
                    $minimumQEScore = floatval($minQEString);
                    if ($minimumQEScore < 0 || $minimumQEScore > 1) {
                        $this->result[ 'errors' ][ ] = array( 'code' => -13, 'message' => "Minimum QE score should be a number between 0 and 1." );
                        return;
                    }
                    $newEngine->extra_parameters[ 'minimum_qe' ] = $minimumQEScore;
                }
                
                
                /*$config = array(
                    'new_engine_name' => $this->name,
                    'client_id' => $this->engineData['client_id'],
                    'system_id' => array('someid1' => 'System 1', 'someid2' => 'System 2', 'someid3' => 'System 3', 'someid4' => 'System 4'),
                    'terms_id' => array('someidterms1' => 'Terms 1', 'sometermsid2' => 'Terms 2', 'sometermsid3' => 'Terms 3')
                    );
                
                $this->result['data']['config'] = $config;
                return;*/

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

        } elseif ( $newEngine instanceof EnginesModel_LetsMTStruct && empty($this->engineData[ 'system_id' ])){
            // the user has not selected a translation system. only the User ID and the engine's name has been entered
            // get the list of available systems and return it to the user
            
            $temp_engine = Engine::getInstance( $result->id );
            $config = $temp_engine->getConfigStruct();
            $config[ 'source' ]  = "en-US"; // TODO replace with values from the project being currently created
            $config[ 'target' ]  = "lv-LV";
            $systemList = $temp_engine->getSystemList($config);
            
            $engineDAO->delete($result); // delete the newly added engine. this is the first time in engineController::add()
                                         // and the user has not yet selected a translation system
            if ( isset( $systemList['error']['code'] ) ) {
                $this->result[ 'errors' ][ ] = $systemList['error'];
                return;
            }
            
            $uiConfig = array(
                'client_id' => array('value' => $this->engineData['client_id']),
                'system_id' => array(),
                'terms_id' => array()
            );
            foreach ($systemList as $systemID => $systemInfo){
                $uiConfig['system_id'][$systemID] = array('value' => $systemInfo['name'],
                                                          'data'  => $systemInfo['metadata']
                                                    );
            }
            
            $this->result['name'] = $this->name;
            $this->result['data']['config'] = $uiConfig;
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
    
    /**
     * This method creates a temporary engine and executes one of it's methods
     */
    private function execute() {

        $tempEngine = null;
        $validEngine = true;

        switch ( strtolower( $this->provider ) ) {
            case strtolower( Constants_Engines::LETSMT ):

                /**
                 * Create a record of type LetsMT
                 */
                $tempEngineRecord = EnginesModel_LetsMTStruct::getStruct();

                $tempEngineRecord->name                                = $this->name;
                $tempEngineRecord->uid                                 = $this->uid;
                $tempEngineRecord->type                                = Constants_Engines::MT;
                $tempEngineRecord->extra_parameters[ 'client_id' ]     = $this->engineData['client_id'];
                $tempEngineRecord->extra_parameters[ 'system_id' ]     = $this->engineData[ 'system_id' ];
                //$tempEngineRecord->extra_parameters[ 'terms_id' ]      = $this->engineData[ 'terms_id' ];
                
                break;
            default:
                $validEngine = false;
        }

        if( !$validEngine ){
            $this->result[ 'errors' ][ ] = array( 'code' => -4, 'message' => "Engine not allowed" );
            return;
        }
        
        $tempEngine = Engine::createTempInstance($tempEngineRecord);
        if(! $tempEngine instanceof Engines_AbstractEngine){
            $this->result[ 'errors' ][ ] = array( 'code' => -12, 'message' => "Creating engine failed. Generic error" );
            return;
        }
        $functionParams = $this->engineData['functionParams'];

        $function = $this->engineData[ 'function' ];
        if(empty($function)){
            $this->result[ 'errors' ][ ] = array( 'code' => -10, 'message' => "No function specified" );
            return;
        } elseif (empty(self::$allowed_execute_functions[strtolower($this->provider)])
                || !in_array($function, self::$allowed_execute_functions[strtolower($this->provider)])){
            $this->result[ 'errors' ][ ] = array( 'code' => -11, 'message' => "Function not allowed" );
            return;
        }

        $executeResult = $tempEngine->$function($functionParams);
        if ( isset( $executeResult['error']['code'] ) ) {
                $this->result[ 'errors' ][ ] = $executeResult['error'];
                return;
        }
        $this->result['data']['result'] = $executeResult;
    }

}