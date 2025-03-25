<?php

use Engines\MMT\MMTServiceApiException;
use EnginesModel\DeepLStruct;
use EnginesModel\LaraStruct;
use Lara\LaraException;
use Users\MetadataDao;
use Utils\Engines\Lara;
use Validator\DeepLValidator;

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

    private static $allowed_actions           = [
            'add', 'delete', 'execute'
    ];
    private static $allowed_execute_functions = [
        // 'letsmt' => [ 'getTermList' ] // letsmt no longer requires this function. it's left as an example
    ];

    public function __construct() {

        parent::__construct();

        //Session Enabled
        $this->identifyUser();
        //Session Disabled

        $filterArgs = [
                'exec'     => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW
                ],
                'id'       => [
                        'filter' => FILTER_SANITIZE_NUMBER_INT
                ],
                'name'     => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW
                ],
                'data'     => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW | FILTER_FLAG_NO_ENCODE_QUOTES
                ],
                'provider' => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW
                ]
        ];

        $postInput = filter_input_array( INPUT_POST, $filterArgs );

        $this->exec       = $postInput[ 'exec' ];
        $this->id         = $postInput[ 'id' ];
        $this->name       = $postInput[ 'name' ];
        $this->provider   = $postInput[ 'provider' ];
        $this->engineData = json_decode( $postInput[ 'data' ], true );

        if ( is_null( $this->exec ) ) {
            $this->result[ 'errors' ][] = [ 'code' => -1, 'message' => "Exec field required" ];

        } else {
            if ( !in_array( $this->exec, self::$allowed_actions ) ) {
                $this->result[ 'errors' ][] = [ 'code' => -2, 'message' => "Exec value not allowed" ];
            }
        }

        //ONLY LOGGED USERS CAN PERFORM ACTIONS ON KEYS
        if ( !$this->userIsLogged ) {
            $this->result[ 'errors' ][] = [
                    'code'    => -3,
                    'message' => "Login is required to perform this action"
            ];
        }

    }

    /**
     * When Called it perform the controller action to retrieve/manipulate data
     *
     * @return mixed
     * @throws Exception
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
     * @throws Exception
     */
    private function add() {

        $validEngine = true;

        switch ( strtolower( $this->provider ) ) {

            case strtolower( Constants_Engines::DEEPL ):

                $newEngineStruct = DeepLStruct::getStruct();

                $newEngineStruct->name                                 = $this->name;
                $newEngineStruct->uid                                  = $this->user->uid;
                $newEngineStruct->type                                 = Constants_Engines::MT;
                $newEngineStruct->extra_parameters[ 'DeepL-Auth-Key' ] = $this->engineData[ 'client_id' ];

                try {
                    DeepLValidator::validate( $newEngineStruct );
                } catch ( Exception $e ) {
                    $this->result[ 'errors' ][] = [ 'code' => $e->getCode(), 'message' => $e->getMessage() ];

                    return;
                }

                break;


            case strtolower( Constants_Engines::MICROSOFT_HUB ):

                /**
                 * Create a record of type MicrosoftHub
                 */
                $newEngineStruct = EnginesModel_MicrosoftHubStruct::getStruct();

                $newEngineStruct->name                            = $this->name;
                $newEngineStruct->uid                             = $this->user->uid;
                $newEngineStruct->type                            = Constants_Engines::MT;
                $newEngineStruct->extra_parameters[ 'client_id' ] = $this->engineData[ 'client_id' ];
                $newEngineStruct->extra_parameters[ 'category' ]  = $this->engineData[ 'category' ];
                break;

            case strtolower( Constants_Engines::APERTIUM ):

                /**
                 * Create a record of type APERTIUM
                 */
                $newEngineStruct = EnginesModel_ApertiumStruct::getStruct();

                $newEngineStruct->name                                = $this->name;
                $newEngineStruct->uid                                 = $this->user->uid;
                $newEngineStruct->type                                = Constants_Engines::MT;
                $newEngineStruct->extra_parameters[ 'client_secret' ] = $this->engineData[ 'secret' ];

                break;

            case strtolower( Constants_Engines::ALTLANG ):

                /**
                 * Create a record of type ALTLANG
                 */
                $newEngineStruct = EnginesModel_AltlangStruct::getStruct();

                $newEngineStruct->name                                = $this->name;
                $newEngineStruct->uid                                 = $this->user->uid;
                $newEngineStruct->type                                = Constants_Engines::MT;
                $newEngineStruct->extra_parameters[ 'client_secret' ] = $this->engineData[ 'secret' ];

                break;

            case strtolower( Constants_Engines::SMART_MATE ):

                /**
                 * Create a record of type SmartMate
                 */
                $newEngineStruct = EnginesModel_SmartMATEStruct::getStruct();

                $newEngineStruct->name                                = $this->name;
                $newEngineStruct->uid                                 = $this->user->uid;
                $newEngineStruct->type                                = Constants_Engines::MT;
                $newEngineStruct->extra_parameters[ 'client_id' ]     = $this->engineData[ 'client_id' ];
                $newEngineStruct->extra_parameters[ 'client_secret' ] = $this->engineData[ 'secret' ];

                break;

            case strtolower( Constants_Engines::YANDEX_TRANSLATE ):

                /**
                 * Create a record of type YandexTranslate
                 */
                $newEngineStruct = EnginesModel_YandexTranslateStruct::getStruct();

                $newEngineStruct->name                                = $this->name;
                $newEngineStruct->uid                                 = $this->user->uid;
                $newEngineStruct->type                                = Constants_Engines::MT;
                $newEngineStruct->extra_parameters[ 'client_secret' ] = $this->engineData[ 'secret' ];

                break;

            case strtolower( Constants_Engines::GOOGLE_TRANSLATE ):

                /**
                 * Create a record of type GoogleTranslate
                 */
                $newEngineStruct = EnginesModel_GoogleTranslateStruct::getStruct();

                $newEngineStruct->name                                = $this->name;
                $newEngineStruct->uid                                 = $this->user->uid;
                $newEngineStruct->type                                = Constants_Engines::MT;
                $newEngineStruct->extra_parameters[ 'client_secret' ] = $this->engineData[ 'secret' ];

                break;

            case strtolower( Constants_Engines::INTENTO ):
                /**
                 * Create a record of type Intento
                 */
                $newEngineStruct                                         = EnginesModel_IntentoStruct::getStruct();
                $newEngineStruct->name                                   = $this->name;
                $newEngineStruct->uid                                    = $this->user->uid;
                $newEngineStruct->type                                   = Constants_Engines::MT;
                $newEngineStruct->extra_parameters[ 'apikey' ]           = $this->engineData[ 'secret' ];
                $newEngineStruct->extra_parameters[ 'provider' ]         = $this->engineData[ 'provider' ];
                $newEngineStruct->extra_parameters[ 'providerkey' ]      = $this->engineData[ 'providerkey' ];
                $newEngineStruct->extra_parameters[ 'providercategory' ] = $this->engineData[ 'providercategory' ];
                break;

            case strtolower( Constants_Engines::LARA ):
                /**
                 * Create a record of type Lara
                 */
                $newEngineStruct = LaraStruct::getStruct();

                $newEngineStruct->uid                                        = $this->user->uid;
                $newEngineStruct->type                                       = Constants_Engines::MT;
                $newEngineStruct->extra_parameters[ 'Lara-AccessKeyId' ]     = $this->engineData[ 'lara-access-key-id' ];
                $newEngineStruct->extra_parameters[ 'Lara-AccessKeySecret' ] = $this->engineData[ 'secret' ];
                $newEngineStruct->extra_parameters[ 'MMT-License' ]          = $this->engineData[ 'mmt-license' ];

                break;

            default:

                // MMT
                $validEngine = $newEngineStruct = $this->featureSet->filter( 'buildNewEngineStruct', false, (object)[
                        'featureSet'   => $this->featureSet,
                        'providerName' => $this->provider,
                        'logged_user'  => $this->user,
                        'engineData'   => $this->engineData
                ] );
                break;

        }

        if ( !$validEngine ) {
            $this->result[ 'errors' ][] = [ 'code' => -4, 'message' => "Engine not allowed" ];

            return;
        }

        $engineList      = Constants_Engines::getAvailableEnginesList();
        $UserMetadataDao = new MetadataDao();
        $engineEnabled   = $UserMetadataDao->get( $this->user->uid, $newEngineStruct->class_load );

        if ( !empty( $engineEnabled ) ) {
            unset( $engineList[ $newEngineStruct->class_load ] );
        }

        $engineDAO             = new EnginesModel_EngineDAO( Database::obtain() );
        $newCreatedDbRowStruct = null;

        if ( array_search( $newEngineStruct->class_load, $engineList ) ) {
            $newEngineStruct->active = true;
            $newCreatedDbRowStruct   = $engineDAO->create( $newEngineStruct );
            $this->destroyUserEnginesCache();
        }

        if ( !$newCreatedDbRowStruct instanceof EnginesModel_EngineStruct ) {

            $engine_type = explode( "\\", $newEngineStruct->class_load );
            $engine_type = array_pop( $engine_type );
            $this->result[ 'errors' ][] = $this->featureSet->filter(
                    'engineCreationFailed',
                    [ 'code' => 403, 'message' => "Creation failed. Only one $engine_type engine is allowed." ],
                    $newEngineStruct->class_load
            );

            return;
        }

        if ( $newEngineStruct instanceof EnginesModel_MicrosoftHubStruct ) {

            $newTestCreatedMT    = Engine::createTempInstance( $newCreatedDbRowStruct );
            $config              = $newTestCreatedMT->getConfigStruct();
            $config[ 'segment' ] = "Hello World";
            $config[ 'source' ]  = "en-US";
            $config[ 'target' ]  = "it-IT";

            $mt_result = $newTestCreatedMT->get( $config );

            if ( isset( $mt_result[ 'error' ][ 'code' ] ) ) {
                $this->result[ 'errors' ][] = [ 'code' => 404, 'message' => $mt_result[ 'error' ] ];
                $engineDAO->delete( $newCreatedDbRowStruct );
                $this->destroyUserEnginesCache();

                return;
            }

        } elseif( $newEngineStruct instanceof EnginesModel_IntentoStruct ){

            $newTestCreatedMT    = Engine::createTempInstance( $newCreatedDbRowStruct );
            $config              = $newTestCreatedMT->getEngineRecord()->getExtraParamsAsArray();
            $config[ 'segment' ] = "Hello World";
            $config[ 'source' ]  = "en-US";
            $config[ 'target' ]  = "fr-FR";

            $mt_result = $newTestCreatedMT->get( $config );

            if ( isset( $mt_result[ 'error' ][ 'code' ] ) ) {
                $this->result[ 'errors' ][] = [ 'code' => 404, 'message' => "Intento license not valid, please verify its validity and try again" ];
                $engineDAO->delete( $newCreatedDbRowStruct );
                $this->destroyUserEnginesCache();

                return;
            }

        } elseif ( $newEngineStruct instanceof EnginesModel_GoogleTranslateStruct ) {

            $newTestCreatedMT    = Engine::createTempInstance( $newCreatedDbRowStruct );
            $config              = $newTestCreatedMT->getConfigStruct();
            $config[ 'segment' ] = "Hello World";
            $config[ 'source' ]  = "en-US";
            $config[ 'target' ]  = "fr-FR";
            $config[ 'key' ]     = $this->engineData[ 'secret' ] ?? null;

            $mt_result = $newTestCreatedMT->get( $config );

            if ( isset( $mt_result[ 'error' ][ 'code' ] ) ) {
                $this->result[ 'errors' ][] = [ 'code' => 404, 'message' => $mt_result[ 'error' ] ];
                $engineDAO->delete( $newCreatedDbRowStruct );
                $this->destroyUserEnginesCache();

                return;
            }
        } elseif ( $newEngineStruct instanceof LaraStruct ) {

            /**
             * @var $newTestCreatedMT Lara
             */
            $newTestCreatedMT    = Engine::createTempInstance( $newCreatedDbRowStruct );
            $config              = $newTestCreatedMT->getConfigStruct();
            $config[ 'segment' ] = "Hello World";
            $config[ 'source' ]  = "en-US";
            $config[ 'target' ]  = "it-IT";

            try {
                $newTestCreatedMT->get( $config );
            } catch ( LaraException $e ) {
                $this->result[ 'errors' ][] = [ "code" => $e->getCode(), "message" => $e->getMessage() ];
                $engineDAO->delete( $newCreatedDbRowStruct );
                $this->destroyUserEnginesCache();

                return;
            }

            // Check MMT License
            $mmtLicense = $newTestCreatedMT->getEngineRecord()->getExtraParamsAsArray()['MMT-License'];

            if(!empty($mmtLicense)){
                $mmtClient = Engines\MMT\MMTServiceApi::newInstance()
                    ->setIdentity( "Matecat", ltrim( INIT::$BUILD_NUMBER, 'v' ) )
                    ->setLicense( $mmtLicense );

                try {
                    $mmtClient->me();
                } catch (MMTServiceApiException $e){
                    $this->result[ 'errors' ][] = [ "code" => $e->getCode(), "message" => "ModernMT license not valid, please verify its validity and try again" ];
                    $engineDAO->delete( $newCreatedDbRowStruct );
                    $this->destroyUserEnginesCache();

                    return;
                }
            }

            $UserMetadataDao = new MetadataDao();
            $UserMetadataDao->set( $this->user->uid, $newCreatedDbRowStruct->class_load, $newCreatedDbRowStruct->id );

        } else {

            try {
                $this->featureSet->run( 'postEngineCreation', $newCreatedDbRowStruct, $this->user );
            } catch ( Exception $e ) {
                $this->result[ 'errors' ][] = [ 'code' => $e->getCode(), 'message' => $e->getMessage() ];
                $engineDAO->delete( $newCreatedDbRowStruct );
                $this->destroyUserEnginesCache();

                return;
            }

        }

        $engine_type                             = explode( "\\", $newCreatedDbRowStruct->class_load );
        $this->result[ 'data' ][ 'id' ]          = $newCreatedDbRowStruct->id;
        $this->result[ 'data' ][ 'name' ]        = $newCreatedDbRowStruct->name;
        $this->result[ 'data' ][ 'description' ] = $newCreatedDbRowStruct->description;
        $this->result[ 'data' ][ 'type' ]        = $newCreatedDbRowStruct->type;
        $this->result[ 'data' ][ 'engine_type' ] = array_pop( $engine_type );
    }

    /**
     * This method deletes an engine from a user's keyring
     *
     * @throws Exception
     */
    private function disable() {

        if ( empty( $this->id ) ) {
            $this->result[ 'errors' ][] = [ 'code' => -5, 'message' => "Engine id required" ];

            return;
        }

        $engineToBeDeleted      = EnginesModel_EngineStruct::getStruct();
        $engineToBeDeleted->id  = $this->id;
        $engineToBeDeleted->uid = $this->user->uid;

        $engineDAO = new EnginesModel_EngineDAO( Database::obtain() );
        $result    = $engineDAO->disable( $engineToBeDeleted );
        $this->destroyUserEnginesCache();

        if ( !$result instanceof EnginesModel_EngineStruct ) {
            $this->result[ 'errors' ][] = [ 'code' => -9, 'message' => "Deletion failed. Generic error" ];

            return;
        }

        $engine = Engine::createTempInstance( $result );

        if ( $engine->isAdaptiveMT() ) {
            //retrieve OWNER Engine License
            ( new MetadataDao() )->delete( $this->user->uid, $result->class_load ); // engine_id
        }

        $this->result[ 'data' ][ 'id' ] = $result->id;

    }


    /**
     * Destroy cache for engine users query
     *
     * @throws Exception
     */
    private function destroyUserEnginesCache() {
        $engineDAO            = new EnginesModel_EngineDAO( Database::obtain() );
        $engineStruct         = EnginesModel_EngineStruct::getStruct();
        $engineStruct->uid    = $this->user->uid;
        $engineStruct->active = true;

        $engineDAO->destroyCache( $engineStruct );
    }

}
