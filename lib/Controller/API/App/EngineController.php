<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthenticationError;
use Controller\API\Commons\Exceptions\AuthorizationError;
use Controller\API\Commons\Validators\LoginValidator;
use DomainException;
use Exception;
use InvalidArgumentException;
use Lara\LaraException;
use Model\DataAccess\Database;
use Model\Engines\EngineDAO;
use Model\Engines\Structs\AltlangStruct;
use Model\Engines\Structs\ApertiumStruct;
use Model\Engines\Structs\DeepLStruct;
use Model\Engines\Structs\EngineStruct;
use Model\Engines\Structs\GoogleTranslateStruct;
use Model\Engines\Structs\IntentoStruct;
use Model\Engines\Structs\LaraStruct;
use Model\Engines\Structs\MicrosoftHubStruct;
use Model\Engines\Structs\SmartMATEStruct;
use Model\Engines\Structs\YandexTranslateStruct;
use Model\Exceptions\NotFoundException;
use Model\Exceptions\ValidationError;
use Model\Users\MetadataDao;
use ReflectionException;
use RuntimeException;
use Utils\Constants\EngineConstants;
use Utils\Engines\EnginesFactory;
use Utils\Engines\Lara;
use Utils\Engines\MMT\MMTServiceApi;
use Utils\Engines\MMT\MMTServiceApiException;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\TaskRunner\Exceptions\ReQueueException;
use Utils\Validator\DeepLValidator;

class EngineController extends KleinController
{

    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * @throws ReQueueException
     * @throws AuthenticationError
     * @throws ValidationError
     * @throws NotFoundException
     * @throws EndQueueException
     * @throws ReflectionException
     * @throws Exception
     */
    public function add(): void
    {
        $request = $this->validateTheRequest();

        $name       = $request[ 'name' ];
        $engineData = $request[ 'data' ];
        $provider   = $request[ 'provider' ];

        if (empty($name)) {
            throw new InvalidArgumentException("Engine name required", -6);
        }

        if (empty($engineData)) {
            throw new InvalidArgumentException("Engine data required", -7);
        }

        if (empty($provider)) {
            throw new InvalidArgumentException("Engine provider required", -8);
        }

        $validEngine = true;
        switch (strtolower($provider)) {
            case strtolower(EngineConstants::DEEPL):

                $newEngineStruct = DeepLStruct::getStruct();

                $newEngineStruct->name                                 = $name;
                $newEngineStruct->uid                                  = $this->user->uid;
                $newEngineStruct->type                                 = EngineConstants::MT;
                $newEngineStruct->extra_parameters[ 'DeepL-Auth-Key' ] = $engineData[ 'client_id' ];

                DeepLValidator::validate($newEngineStruct);

                break;


            case strtolower(EngineConstants::MICROSOFT_HUB):

                /**
                 * Create a record of type MicrosoftHub
                 */
                $newEngineStruct = MicrosoftHubStruct::getStruct();

                $newEngineStruct->name                            = $name;
                $newEngineStruct->uid                             = $this->user->uid;
                $newEngineStruct->type                            = EngineConstants::MT;
                $newEngineStruct->extra_parameters[ 'client_id' ] = $engineData[ 'client_id' ];
                $newEngineStruct->extra_parameters[ 'category' ]  = $engineData[ 'category' ];
                break;

            case strtolower(EngineConstants::APERTIUM):

                /**
                 * Create a record of type APERTIUM
                 */
                $newEngineStruct = ApertiumStruct::getStruct();

                $newEngineStruct->name                                = $name;
                $newEngineStruct->uid                                 = $this->user->uid;
                $newEngineStruct->type                                = EngineConstants::MT;
                $newEngineStruct->extra_parameters[ 'client_secret' ] = $engineData[ 'secret' ];

                break;

            case strtolower(EngineConstants::ALTLANG):

                /**
                 * Create a record of type ALTLANG
                 */
                $newEngineStruct = AltlangStruct::getStruct();

                $newEngineStruct->name                                = $name;
                $newEngineStruct->uid                                 = $this->user->uid;
                $newEngineStruct->type                                = EngineConstants::MT;
                $newEngineStruct->extra_parameters[ 'client_secret' ] = $engineData[ 'secret' ];

                break;

            case strtolower(EngineConstants::SMART_MATE):

                /**
                 * Create a record of type SmartMate
                 */
                $newEngineStruct = SmartMATEStruct::getStruct();

                $newEngineStruct->name                                = $name;
                $newEngineStruct->uid                                 = $this->user->uid;
                $newEngineStruct->type                                = EngineConstants::MT;
                $newEngineStruct->extra_parameters[ 'client_id' ]     = $engineData[ 'client_id' ];
                $newEngineStruct->extra_parameters[ 'client_secret' ] = $engineData[ 'secret' ];

                break;

            case strtolower(EngineConstants::YANDEX_TRANSLATE):

                /**
                 * Create a record of type YandexTranslate
                 */
                $newEngineStruct = YandexTranslateStruct::getStruct();

                $newEngineStruct->name                                = $name;
                $newEngineStruct->uid                                 = $this->user->uid;
                $newEngineStruct->type                                = EngineConstants::MT;
                $newEngineStruct->extra_parameters[ 'client_secret' ] = $engineData[ 'secret' ];

                break;

            case strtolower(EngineConstants::GOOGLE_TRANSLATE):

                /**
                 * Create a record of type GoogleTranslate
                 */
                $newEngineStruct = GoogleTranslateStruct::getStruct();

                $newEngineStruct->name                                = $name;
                $newEngineStruct->uid                                 = $this->user->uid;
                $newEngineStruct->type                                = EngineConstants::MT;
                $newEngineStruct->extra_parameters[ 'client_secret' ] = $engineData[ 'secret' ];

                break;

            case strtolower(EngineConstants::INTENTO):
                /**
                 * Create a record of type Intento
                 */
                $newEngineStruct                               = IntentoStruct::getStruct();
                $newEngineStruct->name                         = $name;
                $newEngineStruct->uid                          = $this->user->uid;
                $newEngineStruct->type                         = EngineConstants::MT;
                $newEngineStruct->extra_parameters[ 'apikey' ] = $engineData[ 'secret' ];
                break;

            case strtolower(EngineConstants::LARA):
                /**
                 * Create a record of type Lara
                 */
                $newEngineStruct = LaraStruct::getStruct();

                $newEngineStruct->uid                                        = $this->user->uid;
                $newEngineStruct->type                                       = EngineConstants::MT;
                $newEngineStruct->extra_parameters[ 'Lara-AccessKeyId' ]     = $engineData[ 'lara-access-key-id' ];
                $newEngineStruct->extra_parameters[ 'Lara-AccessKeySecret' ] = $engineData[ 'secret' ];
                $newEngineStruct->extra_parameters[ 'MMT-License' ]          = $engineData[ 'mmt-license' ];

                break;

            default:

                // MMT
                $validEngine = $newEngineStruct = $this->featureSet->filter(
                        'buildNewEngineStruct',
                        false,
                        (object)[
                                'featureSet'   => $this->featureSet,
                                'providerName' => $provider,
                                'logged_user'  => $this->user,
                                'engineData'   => $engineData
                        ]
                );
                break;
        }

        if (!$validEngine) {
            throw new DomainException("Engine not allowed", -4);
        }

        $engineList      = EngineConstants::getAvailableEnginesList();
        $UserMetadataDao = new MetadataDao();
        $engineEnabled   = $UserMetadataDao->get($this->user->uid, $newEngineStruct->class_load);

        if (!empty($engineEnabled)) {
            unset($engineList[ $newEngineStruct->class_load ]);
        }

        $engineDAO             = new EngineDAO(Database::obtain());
        $newCreatedDbRowStruct = null;

        if (array_search($newEngineStruct->class_load, $engineList)) {
            $newEngineStruct->active = true;
            $newCreatedDbRowStruct   = $engineDAO->create($newEngineStruct);
            $this->destroyUserEnginesCache();
        }

        $engine_type = explode("\\", $newEngineStruct->class_load);
        $engine_type = array_pop($engine_type);

        if (!$newCreatedDbRowStruct instanceof EngineStruct) {
            throw new AuthorizationError("Creation failed. Only one $engine_type engine is allowed.", 403);
        }

        if ($newEngineStruct instanceof MicrosoftHubStruct) {
            $newTestCreatedMT    = EnginesFactory::createTempInstance($newCreatedDbRowStruct);
            $config              = $newTestCreatedMT->getConfigStruct();
            $config[ 'segment' ] = "Hello World";
            $config[ 'source' ]  = "en-US";
            $config[ 'target' ]  = "it-IT";

            $mt_result = $newTestCreatedMT->get($config);

            if (isset($mt_result[ 'error' ][ 'code' ])) {
                $engineDAO->delete($newCreatedDbRowStruct);
                $this->destroyUserEnginesCache();

                throw new DomainException($mt_result[ 'error' ]);
            }
        } elseif ($newEngineStruct instanceof IntentoStruct) {
            $newTestCreatedMT    = EnginesFactory::createTempInstance($newCreatedDbRowStruct);
            $config              = $newTestCreatedMT->getEngineRecord()->getExtraParamsAsArray();
            $config[ 'segment' ] = "Hello World";
            $config[ 'source' ]  = "en-US";
            $config[ 'target' ]  = "fr-FR";

            $mt_result = $newTestCreatedMT->get($config);

            if (isset($mt_result[ 'error' ][ 'code' ])) {
                switch ($mt_result[ 'error' ][ 'code' ]) {
                    // wrong provider credentials
                    case -2:
                        $code    = $mt_result[ 'error' ][ 'http_code' ] ?? 413;
                        $message = $mt_result[ 'error' ][ 'message' ];
                        break;

                    // not valid license
                    case -403:
                        $code    = 413;
                        $message = "The Intento license you entered cannot be used inside CAT tools. Please subscribe to a suitable license to start using Intento as MT engine.";
                        break;

                    default:
                        $code    = 500;
                        $message = "Intento license not valid, please verify its validity and try again";
                        break;
                }

                $engineDAO->delete($newCreatedDbRowStruct);
                $this->destroyUserEnginesCache();

                throw new DomainException($message, $code);
            }
        } elseif ($newEngineStruct instanceof GoogleTranslateStruct) {
            $newTestCreatedMT    = EnginesFactory::createTempInstance($newCreatedDbRowStruct);
            $config              = $newTestCreatedMT->getConfigStruct();
            $config[ 'segment' ] = "Hello World";
            $config[ 'source' ]  = "en-US";
            $config[ 'target' ]  = "fr-FR";
            $config[ 'key' ]     = $newTestCreatedMT->client_secret ?? null;

            $mt_result = $newTestCreatedMT->get($config);

            if (isset($mt_result[ 'error' ][ 'code' ])) {
                $engineDAO->delete($newCreatedDbRowStruct);
                $this->destroyUserEnginesCache();

                throw new DomainException($mt_result[ 'error' ][ 'message' ]);
            }
        } elseif ($newEngineStruct instanceof LaraStruct) {
            /**
             * @var $newTestCreatedMT Lara
             */
            $newTestCreatedMT    = EnginesFactory::createTempInstance($newCreatedDbRowStruct);
            $config              = $newTestCreatedMT->getConfigStruct();
            $config[ 'segment' ] = "Hello World";
            $config[ 'source' ]  = "en-US";
            $config[ 'target' ]  = "it-IT";

            try {
                $newTestCreatedMT->get($config);
            } catch (LaraException $e) {
                $code    = $e->getCode();
                $message = $e->getMessage();
                $engineDAO->delete($newCreatedDbRowStruct);
                $this->destroyUserEnginesCache();

                throw new DomainException($message, $code);
            }

            // Check MMT License
            $mmtLicense = $newTestCreatedMT->getEngineRecord()->getExtraParamsAsArray()[ 'MMT-License' ];

            if (!empty($mmtLicense)) {
                $mmtClient = MMTServiceApi::newInstance()
                        ->setIdentity("Matecat", ltrim(AppConfig::$BUILD_NUMBER, 'v'))
                        ->setLicense($mmtLicense);

                try {
                    $mmtClient->me();
                } catch (MMTServiceApiException $e) {
                    $code    = $e->getCode();
                    $message = "ModernMT license not valid, please verify its validity and try again";
                    $engineDAO->delete($newCreatedDbRowStruct);
                    $this->destroyUserEnginesCache();

                    throw new DomainException($message, $code);
                }
            }

            $UserMetadataDao = new MetadataDao();
            $UserMetadataDao->set($this->user->uid, $newCreatedDbRowStruct->class_load, $newCreatedDbRowStruct->id);
        } else {
            try {
                $this->featureSet->run('postEngineCreation', $newCreatedDbRowStruct, $this->user);
            } catch (Exception $e) {
                $engineDAO->delete($newCreatedDbRowStruct);
                $this->destroyUserEnginesCache();

                throw new DomainException($e->getMessage(), $e->getCode());
            }
        }

        $this->response->json([
                'data'   => [
                        'id'          => $newCreatedDbRowStruct->id,
                        'name'        => $newCreatedDbRowStruct->name,
                        'description' => $newCreatedDbRowStruct->description,
                        'type'        => $newCreatedDbRowStruct->type,
                        'extra'       => $newEngineStruct->extra_parameters,
                        'engine_type' => $engine_type,
                ],
                'errors' => [],
        ]);
    }

    /**
     * @throws Exception
     */
    public function disable(): void
    {
        $request = $this->validateTheRequest();
        $id      = $request[ 'id' ];

        if (empty($id)) {
            throw new InvalidArgumentException("Engine id required", -5);
        }

        $engineToBeDeleted      = EngineStruct::getStruct();
        $engineToBeDeleted->id  = $id;
        $engineToBeDeleted->uid = $this->user->uid;

        $engineDAO = new EngineDAO(Database::obtain());
        $result    = $engineDAO->disable($engineToBeDeleted);
        $this->destroyUserEnginesCache();

        if (!$result instanceof EngineStruct) {
            throw new RuntimeException("Deletion failed. Generic error", -9);
        }

        $engine = EnginesFactory::createTempInstance($result);

        if ($engine->isAdaptiveMT()) {
            $engName = explode("\\", $result->class_load);
            //retrieve OWNER EnginesFactory License
            (new MetadataDao())->delete($this->user->uid, array_pop($engName)); // engine_id
        }

        $this->response->json([
                'data'   => [
                        'id' => $result->id
                ],
                'errors' => []
        ]);
    }

    /**
     * @return array
     */
    private function validateTheRequest(): array
    {
        $id       = filter_var($this->request->param('id'), FILTER_SANITIZE_SPECIAL_CHARS);
        $name     = filter_var($this->request->param('name'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW]);
        $data     = filter_var($this->request->param('data'), FILTER_SANITIZE_FULL_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW | FILTER_FLAG_NO_ENCODE_QUOTES]);
        $provider = filter_var($this->request->param('provider'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW]);

        return [
                'id'       => $id,
                'name'     => $name,
                'data'     => json_decode($data, true),
                'provider' => $provider,
        ];
    }

    /**
     * Destroy cache for engine users query
     *
     * @throws Exception
     */
    private function destroyUserEnginesCache(): void
    {
        $engineDAO            = new EngineDAO(Database::obtain());
        $engineStruct         = EngineStruct::getStruct();
        $engineStruct->uid    = $this->user->uid;
        $engineStruct->active = true;

        $engineDAO->destroyCache($engineStruct);
    }
}

