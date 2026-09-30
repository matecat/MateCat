<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use InvalidArgumentException;
use Model\Engines\Structs\EngineStruct;
use Model\Exceptions\NotFoundException;
use Model\TmKeyManagement\MemoryKeyDao;
use Model\TmKeyManagement\MemoryKeyStruct;
use Model\Users\ClientUserFacade;
use Model\Users\MetadataDao;
use Model\Users\UserStruct;
use ReflectionException;
use RuntimeException;
use TypeError;
use Utils\Constants\EngineConstants;
use Utils\Engines\AbstractEngine;
use Utils\Engines\EnginesFactory;
use Utils\Logger\LoggerFactory;
use Utils\TmKeyManagement\TmKeyManager;
use Utils\TmKeyManagement\TmKeyStruct;
use Utils\TMS\TMSService;
use Utils\Tools\Utils;
use Utils\Validation\UserSuppliedName;

class UserKeysController extends KleinController
{

    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    public function delete(): void
    {
        $request = $this->validateTheRequest();
        $memoryKeyToUpdate = $this->getMemoryToUpdate($request['key'], $request['description']);
        $mkDao = $this->getMkDao();
        $userMemoryKeys = $mkDao->disable($memoryKeyToUpdate);
        $this->removeKeyFromEngines($memoryKeyToUpdate, $request['remove_from']);

        $this->response->json([
            'errors' => [],
            'data' => $userMemoryKeys,
            "success" => true
        ]);
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    public function update(): void
    {
        $request = $this->validateTheRequest();
        $memoryKeyToUpdate = $this->getMemoryToUpdate($request['key'], $request['description']);
        $mkDao = $this->getMkDao();
        $userMemoryKeys = $mkDao->atomicUpdate($memoryKeyToUpdate);

        $this->response->json([
            'errors' => [],
            'data' => $userMemoryKeys,
            "success" => true
        ]);
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    public function newKey(): void
    {
        $request = $this->validateTheRequest();
        $memoryKeyToUpdate = $this->getMemoryToUpdate($request['key'], $request['description']);
        $mkDao = $this->getMkDao();
        $userMemoryKeys = $mkDao->create($memoryKeyToUpdate);

        $this->response->json([
            'errors' => [],
            'data' => $userMemoryKeys,
            "success" => true
        ]);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    public function info(): void
    {
        $request = $this->validateTheRequest();
        $memoryKeyToUpdate = $this->getMemoryToUpdate($request['key'], $request['description']);
        $mkDao = $this->getMkDao();
        $userMemoryKeys = $mkDao->read($memoryKeyToUpdate, true);

        $this->response->json($this->getKeyUsersInfo($userMemoryKeys));
    }

    /**
     * @throws NotFoundException
     * @throws Exception
     * @throws TypeError
     */
    public function share(): void
    {
        $request = $this->validateTheRequest();
        $memoryKeyToUpdate = $this->getMemoryToUpdate($request['key'], $request['description']);
        $emailList = Utils::validateEmailList($request['emails']);
        $mkDao = $this->getMkDao();

        $userMemoryKeys = $mkDao->read($memoryKeyToUpdate) ?: throw new NotFoundException("No user memory keys found");

        (new TmKeyManager())->shareKey(array_values($emailList), $userMemoryKeys[0], $this->user, $this->getDatabase());

        $this->response->json([
            'errors' => [],
            'data' => $userMemoryKeys,
            "success" => true
        ]);
    }

    /**
     * @param array<int, MemoryKeyStruct> $userMemoryKeys
     *
     * @return array<string, mixed>
     */
    protected function getKeyUsersInfo(array $userMemoryKeys): array
    {
        if (empty($userMemoryKeys)) {
            return [
                'errors' => [],
                "data" => [],
                "success" => true
            ];
        }

        $_userStructs = [];
        $tmKey = $userMemoryKeys[0]->tm_key;
        // in_users is a dynamic property set on TmKeyStruct (extends stdClass) by MemoryKeyDao::_buildResult()
        $inUsers = $tmKey !== null ? $tmKey->getInUsers() : [];
        foreach ($inUsers as $user) {
            if ($user instanceof UserStruct) {
                $_userStructs[] = new ClientUserFacade($user);
            }
        }

        return [
            'errors' => [],
            "data" => $_userStructs,
            "success" => true
        ];
    }

    /**
     * @return array<string, mixed>
     * @throws Exception
     */
    private function validateTheRequest(): array
    {
        $key = filter_var($this->request->param('key'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW]);
        $emails = filter_var($this->request->param('emails'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
        // FILTER_UNSAFE_RAW: the name is stored as typed and escaped by each output. The
        // ShareKey email prints it, and that template escapes and defangs every value it is given
        // — see Utils\Email\EmailValue.
        $description = filter_var($this->request->param('description'), FILTER_UNSAFE_RAW);
        $remove_from = filter_var($this->request->param('remove_from'), FILTER_SANITIZE_FULL_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);

        // check for eventual errors on the input passed
        if (empty($key)) {
            throw new InvalidArgumentException("Key missing", -2);
        }

        // This used to refuse any name whose entity-encoded form differed from what was typed —
        // which is every name containing < > & " ' — on the grounds that markup in a resource name
        // is an XSS attempt. It is not: `Smith & Sons` is a resource name, and the reason markup
        // was dangerous here was that the sinks printing it did not escape. They do now, each for
        // its own context, so the name is held to the same rules as every other one instead.
        if ($description !== false && $description !== null && $description !== '') {
            $description = UserSuppliedName::validated(
                $description,
                'description',
                TmKeyManager::RESOURCE_NAME_MAX_LENGTH
            );
        }

        return [
            'key' => $key,
            'emails' => $emails,
            'description' => (!empty($description)) ? $description : null,
            'remove_from' => $remove_from,
        ];
    }

    /**
     * @return MemoryKeyDao
     */
    private function getMkDao(): MemoryKeyDao
    {
        return new MemoryKeyDao($this->getDatabase());
    }

    /**
     * Testability seam: overridable so tests can inject a stub TMSService
     * and avoid firing a live MyMemory API call from checkCorrectKey().
     *
     * @return TMSService
     * @throws Exception
     */
    protected function getTmService(): TMSService
    {
        return new TMSService($this->getDatabase());
    }

    /**
     * @param string $key
     * @param string|null $description
     *
     * @return MemoryKeyStruct
     * @throws Exception
     * @throws TypeError
     */
    private function getMemoryToUpdate(string $key, ?string $description = null): MemoryKeyStruct
    {
        $tmService = $this->getTmService();

        //validate the key
        $tmService->checkCorrectKey($key);

        $tmKeyStruct = new TmKeyStruct();
        $tmKeyStruct->key = $key;
        $tmKeyStruct->name = $description;
        $tmKeyStruct->tm = true;
        $tmKeyStruct->glos = true;

        $memoryKeyToUpdate = new MemoryKeyStruct();
        $memoryKeyToUpdate->uid = (int)$this->user->uid;
        $memoryKeyToUpdate->tm_key = $tmKeyStruct;

        return $memoryKeyToUpdate;
    }

    /**
     * Removes a memory key from specified engines.
     *
     * This method processes a list of engine names provided as a CSV string
     * and attempts to remove the given memory key from each engine. If the engine
     * supports adaptive machine translation (MT), it verifies ownership of the memory
     * key before deletion.
     *
     * @param MemoryKeyStruct $memoryKey The memory key to be removed.
     * @param string|null $enginesListCsv A comma-separated list of engine names
     *                                        from which the memory key should be removed.
     * @throws Exception
     */
    private function removeKeyFromEngines(MemoryKeyStruct $memoryKey, ?string $enginesListCsv = ''): void
    {
        $uid = $this->getUser()->uid ?? throw new Exception('User not authenticated');

        // Convert the CSV string into an array of engine names, filtering out empty values.
        $deleteFrom = array_filter(explode(",", $enginesListCsv ?? ''));

        // Iterate over each engine name in the list.
        foreach ($deleteFrom as $engineName) {
            try {
                // Create a temporary engine instance using the engine name.
                $struct = EngineStruct::getStruct();
                $struct->class_load = $engineName;
                $struct->type = EngineConstants::MT;
                $engine = EnginesFactory::createTempInstance($struct, $this->getDatabase());

                // Check if the engine supports adaptive MT.
                if ($engine->isAdaptiveMT()) {
                    // Retrieve metadata for the engine, ensuring it belongs to the current user.
                     $ownerMmtEngineMetaData = (new MetadataDao($this->getDatabase()))
                         ->setCacheTTL(60 * 60 * 24 * 30) // Cache TTL: 30 days.
                         ->get($uid, $engine->getEngineRecord()->class_load ?? throw new RuntimeException('Missing engine class_load'));

                    // If metadata exists, attempt to delete the memory key from the engine.
                    if (!empty($ownerMmtEngineMetaData) && is_numeric($ownerMmtEngineMetaData->value)) {
                        $engine = EnginesFactory::getInstance((int)$ownerMmtEngineMetaData->value, $this->getDatabase(), AbstractEngine::class);
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
