<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthorizationError;
use Controller\API\Commons\Validators\LoginValidator;
use DomainException;
use Exception;
use InvalidArgumentException;
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
use Model\Engines\Structs\MMTStruct;
use Model\Engines\Structs\SmartMATEStruct;
use Model\Engines\Structs\YandexTranslateStruct;
use Model\Users\MetadataDao;
use ReflectionException;
use RuntimeException;
use Utils\Constants\EngineConstants;
use Utils\Engines\EnginesFactory;
use Utils\Engines\Validators\AltLangEngineValidator;
use Utils\Engines\Validators\DeepLEngineValidator;
use Utils\Engines\Validators\GoogleTranslateEngineValidator;
use Utils\Engines\Validators\IntentoEngineValidator;
use Utils\Engines\Validators\LaraEngineValidator;
use Utils\Engines\Validators\MMTEngineValidator;
use Utils\Validator\Contracts\ValidatorObject;

class EngineController extends KleinController
{

    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    /**
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

        switch (strtolower($provider)) {
            case strtolower(EngineConstants::DEEPL):

                $newEngineStruct = DeepLStruct::getStruct();

                $newEngineStruct->name                                 = $name;
                $newEngineStruct->uid                                  = $this->user->uid;
                $newEngineStruct->type                                 = EngineConstants::MT;
                $newEngineStruct->extra_parameters[ 'DeepL-Auth-Key' ] = $engineData[ 'client_id' ];

                (new DeepLEngineValidator())->validate(ValidatorObject::fromArray(['engineStruct' => $newEngineStruct]));

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

                (new AltlangEngineValidator())->validate(ValidatorObject::fromArray(['engineStruct' => $newEngineStruct]));

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

                (new GoogleTranslateEngineValidator())->validate(ValidatorObject::fromArray(['engineStruct' => $newEngineStruct]));

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

                (new IntentoEngineValidator())->validate(ValidatorObject::fromArray(['engineStruct' => $newEngineStruct]));

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

                (new LaraEngineValidator())->validate(ValidatorObject::fromArray(['engineStruct' => $newEngineStruct]));

                break;

            case strtolower( EngineConstants::MMT ):
                /**
                 * Create a record of type MMT
                 */
                $newEngineStruct = MMTStruct::getStruct();

                $newEngineStruct->uid                                        = $this->user->uid;
                $newEngineStruct->type                                       = EngineConstants::MT;
                $newEngineStruct->extra_parameters[ 'MMT-License' ]          = $engineData[ 'secret' ];
                $newEngineStruct->extra_parameters[ 'MMT-context-analyzer' ] = true;

                (new MMTEngineValidator())->validate(ValidatorObject::fromArray(['engineStruct' => $newEngineStruct]));

                break;

            default:
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

        } elseif ($newEngineStruct instanceof LaraStruct) {
            $UserMetadataDao = new MetadataDao();
            $UserMetadataDao->set($this->user->uid, $newCreatedDbRowStruct->class_load, $newCreatedDbRowStruct->id);
        } elseif( $newEngineStruct instanceof MMTStruct){
            $UserMetadataDao = new MetadataDao();
            $UserMetadataDao->set($this->user->uid, $newCreatedDbRowStruct->class_load, $newCreatedDbRowStruct->id);
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

