<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use InvalidArgumentException;
use Model\DataAccess\Database;
use Model\Engines\Structs\EngineStruct;
use Model\Exceptions\NotFoundException;
use Model\TmKeyManagement\MemoryKeyDao;
use Model\TmKeyManagement\MemoryKeyStruct;
use Model\Users\ClientUserFacade;
use Model\Users\MetadataDao;
use ReflectionException;
use Utils\Constants\EngineConstants;
use Utils\Engines\EnginesFactory;
use Utils\Logger\LoggerFactory;
use Utils\TmKeyManagement\TmKeyManager;
use Utils\TmKeyManagement\TmKeyStruct;
use Utils\TMS\TMSService;
use Utils\Tools\Utils;

class UserKeysController extends KleinController
{

    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * @throws Exception
     */
    public function delete(): void
    {
        $request           = $this->validateTheRequest();
        $memoryKeyToUpdate = $this->getMemoryToUpdate($request[ 'key' ], $request[ 'description' ]);
        $mkDao             = $this->getMkDao();
        $userMemoryKeys    = $mkDao->disable($memoryKeyToUpdate);
        $this->removeKeyFromEngines($userMemoryKeys, $request[ 'remove_from' ]);

        $this->response->json([
                'errors'  => [],
                'data'    => $userMemoryKeys,
                "success" => true
        ]);
    }

    /**
     * @throws Exception
     */
    public function update(): void
    {
        $request           = $this->validateTheRequest();
        $memoryKeyToUpdate = $this->getMemoryToUpdate($request[ 'key' ], $request[ 'description' ]);
        $mkDao             = $this->getMkDao();
        $userMemoryKeys    = $mkDao->atomicUpdate($memoryKeyToUpdate);

        $this->response->json([
                'errors'  => [],
                'data'    => $userMemoryKeys,
                "success" => true
        ]);
    }

    /**
     * @throws Exception
     */
    public function newKey(): void
    {
        $request           = $this->validateTheRequest();
        $memoryKeyToUpdate = $this->getMemoryToUpdate($request[ 'key' ], $request[ 'description' ]);
        $mkDao             = $this->getMkDao();
        $userMemoryKeys    = $mkDao->create($memoryKeyToUpdate);

        $this->response->json([
                'errors'  => [],
                'data'    => $userMemoryKeys,
                "success" => true
        ]);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function info(): void
    {
        $request           = $this->validateTheRequest();
        $memoryKeyToUpdate = $this->getMemoryToUpdate($request[ 'key' ], $request[ 'description' ]);
        $mkDao             = $this->getMkDao();
        $userMemoryKeys    = $mkDao->read($memoryKeyToUpdate);

        $this->response->json($this->getKeyUsersInfo($userMemoryKeys));
    }

    /**
     * @throws NotFoundException
     * @throws Exception
     */
    public function share(): void
    {
        $request           = $this->validateTheRequest();
        $memoryKeyToUpdate = $this->getMemoryToUpdate($request[ 'key' ], $request[ 'description' ]);
        $emailList         = Utils::validateEmailList($request[ 'emails' ]);
        $mkDao             = $this->getMkDao();

        $userMemoryKeys = $mkDao->read($memoryKeyToUpdate) ?: throw new NotFoundException("No user memory keys found");


        (new TmKeyManager())->shareKey($emailList, $userMemoryKeys[ 0 ], $this->user);

        $this->response->json([
                'errors'  => [],
                'data'    => $userMemoryKeys,
                "success" => true
        ]);
    }

    /**
     * @param array $userMemoryKeys
     *
     * @return array
     */
    protected function getKeyUsersInfo(array $userMemoryKeys): array
    {
        if (empty($userMemoryKeys)) {
            return [
                    'errors'  => [],
                    "data"    => [],
                    "success" => true
            ];
        }

        $_userStructs = [];
        foreach ($userMemoryKeys[ 0 ]->tm_key->getInUsers() as $userStruct) {
            $_userStructs[] = new ClientUserFacade($userStruct);
        }

        return [
                'errors'  => [],
                "data"    => $_userStructs,
                "success" => true
        ];
    }

    /**
     * @return array
     * @throws Exception
     */
    private function validateTheRequest(): array
    {
        $key         = filter_var($this->request->param('key'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
        $emails      = filter_var($this->request->param('emails'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
        $description = filter_var($this->request->param('description'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW]);
        $remove_from = filter_var($this->request->param('remove_from'), FILTER_SANITIZE_FULL_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);

        // check for eventual errors on the input passed
        if (empty($key)) {
            throw new InvalidArgumentException("Key missing", -2);
        }

        // Prevent XSS attack
        // ===========================
        // POC. Try to add this string in the input:
        // <details x=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx:2 open ontoggle="prompt(document.cookie);">
        // in this case, an error MUST be thrown
        if ($this->request->param('description') and $this->request->param('description') !== $description) {
            throw new InvalidArgumentException("<span>Resource names cannot contain the following characters:</span><ul><li><</li><li>\"</li><li>'</li></ul>", -3);
        }

        return [
                'key'         => $key,
                'emails'      => $emails,
                'description' => (!empty($description)) ? $description : null,
                'remove_from' => $remove_from,
        ];
    }

    /**
     * @return MemoryKeyDao
     */
    private function getMkDao(): MemoryKeyDao
    {
        return new MemoryKeyDao(Database::obtain());
    }

    /**
     * @param      $key
     * @param null $description
     *
     * @return MemoryKeyStruct
     * @throws Exception
     */
    private function getMemoryToUpdate($key, $description = null): MemoryKeyStruct
    {
        $tmService = new TMSService();

        //validate the key
        $tmService->checkCorrectKey($key);

        $tmKeyStruct       = new TmKeyStruct();
        $tmKeyStruct->key  = $key;
        $tmKeyStruct->name = $description;
        $tmKeyStruct->tm   = true;
        $tmKeyStruct->glos = true;

        $memoryKeyToUpdate         = new MemoryKeyStruct();
        $memoryKeyToUpdate->uid    = $this->user->uid;
        $memoryKeyToUpdate->tm_key = $tmKeyStruct;

        return $memoryKeyToUpdate;
    }

    /**
     * Removes a memory key from specified engines.
     *
     * This method processes a list of engine names provided as a CSV string,
     * and attempts to remove the given memory key from each engine. If the engine
     * supports adaptive machine translation (MT), it verifies ownership of the memory
     * key before deletion.
     *
     * @param MemoryKeyStruct $memoryKey      The memory key to be removed.
     * @param string|null     $enginesListCsv A comma-separated list of engine names
     *                                        from which the memory key should be removed.
     */
    private function removeKeyFromEngines(MemoryKeyStruct $memoryKey, ?string $enginesListCsv = '')
    {
        // Convert the CSV string into an array of engine names, filtering out empty values.
        $deleteFrom = array_filter(explode(",", $enginesListCsv));

        // Iterate over each engine name in the list.
        foreach ($deleteFrom as $engineName) {
            try {
                // Create a temporary engine instance using the engine name.
                $struct             = EngineStruct::getStruct();
                $struct->class_load = $engineName;
                $struct->type       = EngineConstants::MT;
                $engine             = EnginesFactory::createTempInstance($struct);

                // Check if the engine supports adaptive MT.
                if ($engine->isAdaptiveMT()) {
                    // Retrieve metadata for the engine, ensuring it belongs to the current user.
                    $ownerMmtEngineMetaData = (new MetadataDao())
                            ->setCacheTTL(60 * 60 * 24 * 30) // Cache TTL: 30 days.
                            ->get($this->getUser()->uid, $engine->getEngineRecord()->class_load);

                    // If metadata exists, attempt to delete the memory key from the engine.
                    if (!empty($ownerMmtEngineMetaData)) {
                        $engine    = EnginesFactory::getInstance($ownerMmtEngineMetaData->value);
                        $engineKey = $engine->getMemoryIfMine($memoryKey);
                        if ($engineKey) {
                            $engine->deleteMemory($engineKey);
                        }
                    }
                }
            } catch (Exception $e) {
                // Log any exceptions that occur during the process.
                LoggerFactory::getLogger('engines')->debug($e->getMessage());
            }
        }
    }

}