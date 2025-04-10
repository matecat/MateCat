<?php

namespace API\App;

use API\Commons\KleinController;
use API\Commons\Validators\LoginValidator;
use Constants_Engines;
use Database;
use DomainException;
use Engine;
use EnginesModel\DeepLStruct;
use EnginesModel\LaraStruct;
use EnginesModel_AltlangStruct;
use EnginesModel_ApertiumStruct;
use EnginesModel_EngineDAO;
use EnginesModel_EngineStruct;
use EnginesModel_GoogleTranslateStruct;
use EnginesModel_IntentoStruct;
use EnginesModel_MicrosoftHubStruct;
use EnginesModel_SmartMATEStruct;
use EnginesModel_YandexTranslateStruct;
use Exception;
use InvalidArgumentException;
use Klein\Response;
use Lara\LaraException;
use RuntimeException;
use Users\MetadataDao;
use Utils\Engines\Lara;
use Validator\DeepLValidator;

class EngineController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function add(): Response
    {
        $request = $this->validateTheRequest();

        $name = $request['name'];
        $engineData = $request['data'];
        $provider = $request['provider'];

        if ( empty( $name ) ) {
            throw new InvalidArgumentException("Engine name required", -6);
        }

        if ( empty( $engineData ) ) {
            throw new InvalidArgumentException("Engine data required", -7);
        }

        if ( empty( $provider ) ) {
            throw new InvalidArgumentException("Engine provider required", -8);
        }

        $newEngineStruct = null;
        $validEngine     = true;

        try {
            switch ( strtolower( $provider ) ) {

                case strtolower( Constants_Engines::DEEPL ):

                    $newEngineStruct = DeepLStruct::getStruct();

                    $newEngineStruct->name                                 = $name;
                    $newEngineStruct->uid                                  = $this->user->uid;
                    $newEngineStruct->type                                 = Constants_Engines::MT;
                    $newEngineStruct->extra_parameters[ 'DeepL-Auth-Key' ] = $engineData[ 'client_id' ];

                    DeepLValidator::validate($newEngineStruct);

                    break;


                case strtolower( Constants_Engines::MICROSOFT_HUB ):

                    /**
                     * Create a record of type MicrosoftHub
                     */
                    $newEngineStruct = EnginesModel_MicrosoftHubStruct::getStruct();

                    $newEngineStruct->name                                = $name;
                    $newEngineStruct->uid                                 = $this->user->uid;
                    $newEngineStruct->type                                = Constants_Engines::MT;
                    $newEngineStruct->extra_parameters[ 'client_id' ]     = $engineData[ 'client_id' ];
                    $newEngineStruct->extra_parameters[ 'category' ]      = $engineData[ 'category' ];
                    break;

                case strtolower( Constants_Engines::APERTIUM ):

                    /**
                     * Create a record of type APERTIUM
                     */
                    $newEngineStruct = EnginesModel_ApertiumStruct::getStruct();

                    $newEngineStruct->name                                = $name;
                    $newEngineStruct->uid                                 = $this->user->uid;
                    $newEngineStruct->type                                = Constants_Engines::MT;
                    $newEngineStruct->extra_parameters[ 'client_secret' ] = $engineData[ 'secret' ];

                    break;

                case strtolower( Constants_Engines::ALTLANG ):

                    /**
                     * Create a record of type ALTLANG
                     */
                    $newEngineStruct = EnginesModel_AltlangStruct::getStruct();

                    $newEngineStruct->name                                = $name;
                    $newEngineStruct->uid                                 = $this->user->uid;
                    $newEngineStruct->type                                = Constants_Engines::MT;
                    $newEngineStruct->extra_parameters[ 'client_secret' ] = $engineData[ 'secret' ];

                    break;

                case strtolower( Constants_Engines::SMART_MATE ):

                    /**
                     * Create a record of type SmartMate
                     */
                    $newEngineStruct = EnginesModel_SmartMATEStruct::getStruct();

                    $newEngineStruct->name                                = $name;
                    $newEngineStruct->uid                                 = $this->user->uid;
                    $newEngineStruct->type                                = Constants_Engines::MT;
                    $newEngineStruct->extra_parameters[ 'client_id' ]     = $engineData[ 'client_id' ];
                    $newEngineStruct->extra_parameters[ 'client_secret' ] = $engineData[ 'secret' ];

                    break;

                case strtolower( Constants_Engines::YANDEX_TRANSLATE ):

                    /**
                     * Create a record of type YandexTranslate
                     */
                    $newEngineStruct = EnginesModel_YandexTranslateStruct::getStruct();

                    $newEngineStruct->name                                = $name;
                    $newEngineStruct->uid                                 = $this->user->uid;
                    $newEngineStruct->type                                = Constants_Engines::MT;
                    $newEngineStruct->extra_parameters[ 'client_secret' ] = $engineData[ 'secret' ];

                    break;

                case strtolower( Constants_Engines::GOOGLE_TRANSLATE ):

                    /**
                     * Create a record of type GoogleTranslate
                     */
                    $newEngineStruct = EnginesModel_GoogleTranslateStruct::getStruct();

                    $newEngineStruct->name                                = $name;
                    $newEngineStruct->uid                                 = $this->user->uid;
                    $newEngineStruct->type                                = Constants_Engines::MT;
                    $newEngineStruct->extra_parameters[ 'client_secret' ] = $engineData[ 'secret' ];

                    break;

                case strtolower(Constants_Engines::INTENTO):
                    /**
                     * Create a record of type Intento
                     */
                    $newEngineStruct = EnginesModel_IntentoStruct::getStruct();
                    $newEngineStruct->name                                 = $name;
                    $newEngineStruct->uid                                  = $this->user->uid;
                    $newEngineStruct->type                                 = Constants_Engines::MT;
                    $newEngineStruct->extra_parameters['apikey']           = $engineData['secret'];
                    $newEngineStruct->extra_parameters['provider']         = $engineData['provider'];
                    $newEngineStruct->extra_parameters['providerkey']      = $engineData['providerkey'];
                    $newEngineStruct->extra_parameters['providercategory'] = $engineData['providercategory'];
                    break;

                case strtolower( Constants_Engines::LARA ):
                    /**
                     * Create a record of type Lara
                     */
                    $newEngineStruct = LaraStruct::getStruct();

                    $newEngineStruct->uid                                        = $this->user->uid;
                    $newEngineStruct->type                                       = Constants_Engines::MT;
                    $newEngineStruct->extra_parameters[ 'Lara-AccessKeyId' ]     = $engineData[ 'lara-access-key-id' ];
                    $newEngineStruct->extra_parameters[ 'Lara-AccessKeySecret' ] = $engineData[ 'secret' ];
                    $newEngineStruct->extra_parameters[ 'MMT-License' ]          = $engineData[ 'mmt-license' ];

                    break;

                default:

                    // MMT
                    $validEngine = $newEngineStruct = $this->featureSet->filter( 'buildNewEngineStruct', false, (object)[
                        'featureSet'   => $this->featureSet,
                        'providerName' => $provider,
                        'logged_user'  => $this->user,
                        'engineData'   => $engineData
                    ] );
                    break;
            }

            if ( !$validEngine ) {
                throw new DomainException("Engine not allowed", -4);
            }

            $engineList = $this->featureSet->filter( 'getAvailableEnginesListForUser', Constants_Engines::getAvailableEnginesList(), $this->user );

            $engineDAO             = new EnginesModel_EngineDAO( Database::obtain() );
            $newCreatedDbRowStruct = null;

            if ( array_search( $newEngineStruct->class_load, $engineList ) ) {
                $newEngineStruct->active = true;
                $newCreatedDbRowStruct = $engineDAO->create( $newEngineStruct );
                $this->destroyUserEnginesCache();
            }

            if ( !$newCreatedDbRowStruct instanceof EnginesModel_EngineStruct ) {

                $error = $this->featureSet->filter(
                    'engineCreationFailed',
                    [ 'code' => -9, 'message' => "Creation failed. Generic error" ],
                    $newEngineStruct->class_load
                );

                throw new DomainException($error['message'], $error['code']);
            }

            if ( $newEngineStruct instanceof EnginesModel_MicrosoftHubStruct ) {

                $newTestCreatedMT    = Engine::createTempInstance( $newCreatedDbRowStruct );
                $config              = $newTestCreatedMT->getConfigStruct();
                $config[ 'segment' ] = "Hello World";
                $config[ 'source' ]  = "en-US";
                $config[ 'target' ]  = "it-IT";

                $mt_result = $newTestCreatedMT->get( $config );

                if ( isset( $mt_result[ 'error' ][ 'code' ] ) ) {
                    $engineDAO->delete( $newCreatedDbRowStruct );
                    $this->destroyUserEnginesCache();

                    throw new DomainException($mt_result[ 'error' ]);
                }

            } elseif ( $newEngineStruct instanceof EnginesModel_GoogleTranslateStruct ) {

                $newTestCreatedMT    = Engine::createTempInstance( $newCreatedDbRowStruct );
                $config              = $newTestCreatedMT->getConfigStruct();
                $config[ 'segment' ] = "Hello World";
                $config[ 'source' ]  = "en-US";
                $config[ 'target' ]  = "fr-FR";
                $config[ 'key' ]     = $this->engineData['secret'] ?? null;

                $mt_result = $newTestCreatedMT->get( $config );

                if ( isset( $mt_result[ 'error' ][ 'code' ] ) ) {
                    $engineDAO->delete( $newCreatedDbRowStruct );
                    $this->destroyUserEnginesCache();

                    throw new DomainException($mt_result[ 'error' ]);
                }
            } elseif ( $newEngineStruct instanceof LaraStruct ) {

                /**
                 * @var $newTestCreatedMT Lara
                 */
                $newTestCreatedMT = Engine::createTempInstance( $newCreatedDbRowStruct );

                try {
                    $newTestCreatedMT->getAvailableLanguages();
                } catch ( LaraException $e ) {
                    $engineDAO->delete( $newCreatedDbRowStruct );
                    $this->destroyUserEnginesCache();

                    throw new DomainException($e->getMessage(), $e->getCode());
                }

                $UserMetadataDao = new MetadataDao();
                $UserMetadataDao->set( $this->user->uid, $newCreatedDbRowStruct->class_load, $newCreatedDbRowStruct->id );

            } else {
                try {
                    $this->featureSet->run( 'postEngineCreation', $newCreatedDbRowStruct, $this->user );
                } catch ( Exception $e ) {
                    $engineDAO->delete( $newCreatedDbRowStruct );
                    $this->destroyUserEnginesCache();

                    throw new DomainException($e->getMessage(), $e->getCode());
                }
            }

            return $this->response->json([
                'data' => [
                    'id'          => $newCreatedDbRowStruct->id,
                    'name'        => $newCreatedDbRowStruct->name,
                    'description' => $newCreatedDbRowStruct->description,
                    'type'        => $newCreatedDbRowStruct->type,
                    'engine_type' => $newCreatedDbRowStruct->class_load,
                ],
                'errors' => [],
            ]);

        } catch (Exception $exception){
            return $this->returnException($exception);
        }
    }

    public function disable(): Response
    {
        try {
            $request = $this->validateTheRequest();
            $id = $request['id'];

            if ( empty( $id ) ) {
                throw new InvalidArgumentException("Engine id required", -5);
            }

            $engineToBeDeleted      = EnginesModel_EngineStruct::getStruct();
            $engineToBeDeleted->id  = $id;
            $engineToBeDeleted->uid = $this->user->uid;

            $engineDAO = new EnginesModel_EngineDAO( Database::obtain() );
            $result    = $engineDAO->disable( $engineToBeDeleted );
            $this->destroyUserEnginesCache();

            if ( !$result instanceof EnginesModel_EngineStruct ) {
                throw new RuntimeException("Deletion failed. Generic error", -9);
            }

            $engine = Engine::createTempInstance( $result );

            if ( $engine->isAdaptiveMT() ) {
                //retrieve OWNER Engine License
                ( new MetadataDao() )->delete( $this->user->uid, $result->class_load ); // engine_id
            }

            return $this->response->json([
                'data' => [
                    'id' => $result->id
                ],
                'errors' => []
            ]);

        } catch (Exception $exception){
            return $this->returnException($exception);
        }
    }

    /**
     * @return array
     */
    private function validateTheRequest(): array
    {
        $id = filter_var( $this->request->param( 'id' ), FILTER_SANITIZE_STRING );
        $name = filter_var( $this->request->param( 'name' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW  ] );
        $data = filter_var( $this->request->param( 'data' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW | FILTER_FLAG_NO_ENCODE_QUOTES  ] );
        $provider = filter_var( $this->request->param( 'provider' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW  ] );

        return [
            'id' => $id,
            'name' => $name,
            'data' => json_decode( $data, true ),
            'provider' => $provider,
        ];
    }

    /**
     * Destroy cache for engine users query
     *
     * @throws Exception
     */
    private function destroyUserEnginesCache()
    {
        $engineDAO            = new EnginesModel_EngineDAO( Database::obtain() );
        $engineStruct         = EnginesModel_EngineStruct::getStruct();
        $engineStruct->uid    = $this->user->uid;
        $engineStruct->active = true;

        $engineDAO->destroyCache( $engineStruct );
    }
}

