<?php

namespace Controller\API\V3;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use InvalidArgumentException;
use Model\TmKeyManagement\MemoryKeyDao;
use Model\TmKeyManagement\MemoryKeyStruct;
use Utils\Engines\EnginesFactory;
use Utils\Engines\MyMemory;
use Utils\TmKeyManagement\TmKeyStruct;
use Utils\TMS\TMSService;

class MyMemoryController extends KleinController
{
    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * Create a MM key and assign to the logged user
     *
     * @throws \TypeError
     */
    public function create(): void
    {
        try {
            $body = $this->request->body();
            $json = json_decode((string)$body, true);

            $key = null;

            if (!isset($json['name'])) {
                throw new InvalidArgumentException('Missing `name` param', 403);
            }

            if (isset($json['key'])) {
                $keyFiltered = filter_var($json['key'], FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
                $key = ($keyFiltered !== false) ? $keyFiltered : null;
            }

            $nameFiltered = filter_var($json['name'], FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
            $name = ($nameFiltered !== false) ? $nameFiltered : '';

            if ($key !== null) {
                $newKey = $this->checkTheKeyAndAssignToUser($key, $name);
            } else {
                $newKey = $this->createANewKeyAndAssignToUser($name);
            }

            $this->response->status()->setCode(200);
            $this->response->json([
                'key' => $newKey
            ]);
            exit();
        } catch (Exception $exception) {
            $this->response->status()->setCode($exception->getCode());
            $this->response->json([
                'errors' => [
                    'code' => $exception->getCode(),
                    'message' => $exception->getMessage()
                ]
            ]);
            exit();
        }
    }

    /**
     * @param string $name
     *
     * @return mixed
     * @throws Exception
     * @throws \TypeError
     */
    private function createANewKeyAndAssignToUser(string $name): mixed
    {
        $tms = EnginesFactory::getInstance(1, MyMemory::class);
        $newKey = $tms->createMyMemoryKey();

        $this->saveMemoryKey($newKey->key, $name);

        return $newKey->key;
    }

    /**
     * @param string $key
     * @param string $name
     *
     * @return string
     * @throws Exception
     * @throws \TypeError
     */
    private function checkTheKeyAndAssignToUser(string $key, string $name): string
    {
        $tmxHandler = new TMSService();
        $keyExists = $tmxHandler->checkCorrectKey($key);

        if ($keyExists === false) {
            throw new Exception($key . " is not a valid key");
        }

        $this->saveMemoryKey($key, $name);

        return $key;
    }

    /**
     * @param string $key
     * @param string $name
     *
     * @throws Exception
     * @throws \TypeError
     */
    private function saveMemoryKey(string $key, string $name): void
    {
        $tmKeyStruct = new TmKeyStruct();
        $tmKeyStruct->key = $key;
        $tmKeyStruct->name = $name;
        $tmKeyStruct->tm = true;
        $tmKeyStruct->glos = true;

        $mkDao = new MemoryKeyDao($this->db());

        $newMemoryKey = new MemoryKeyStruct();
        $newMemoryKey->uid = $this->user->uid ?? throw new \TypeError('User UID must not be null');
        $newMemoryKey->tm_key = $tmKeyStruct;

        try {
            $mkDao->create($newMemoryKey);
        } catch (Exception) {
            $mkDao->atomicUpdate($newMemoryKey);
        }
    }
}
