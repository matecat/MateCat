<?php

namespace Controller\API\V3;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use Klein\Exceptions\LockedResponseException;
use Klein\Exceptions\ResponseAlreadySentException;
use Model\TmKeyManagement\MemoryKeyDao;
use Model\TmKeyManagement\MemoryKeyStruct;

class TmKeyManagementController extends KleinController
{

    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * Return all the keys of the user
     *
     * @throws LockedResponseException
     * @throws ResponseAlreadySentException
     */
    public function getByUser(): void
    {
        try {
            $_keyDao = new MemoryKeyDao($this->getDatabase());
            $dh = new MemoryKeyStruct(['uid' => $this->user->uid]);
            $keyList = $_keyDao->read($dh);

            $list = ['tm_keys' => []];
            foreach ($keyList as $key) {
                $list['tm_keys'][] = $key->tm_key;
            }

            $this->response->json($list);
        } catch (Exception $exception) {
            $this->response->status()->setCode(500);
            $this->response->json([
                'errors' => [
                    $exception->getMessage()
                ]
            ]);
        }
    }

}