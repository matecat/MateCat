<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\Commons\Validators\InternalUserValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use Model\ApiKeys\ApiKeyDao;
use Model\ApiKeys\ApiKeyStruct;
use Throwable;
use Utils\Tools\Utils;

class ApiKeyController extends KleinController
{

    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * create an api key for a logged user
     *
     * @throws Exception
     * @throws Throwable
     */
    public function create(): void
    {
        (new InternalUserValidator($this))->validate();
        $apiKeyDao = new ApiKeyDao();
        $uid = $this->getUser()->uid ?? throw new NotFoundException('User not authenticated');

        // check if the logged user already has a key
        !$apiKeyDao->getByUid($uid) ?: throw new NotFoundException('The user has not a valid API key');

        // generate key
        $generatedKey = $apiKeyDao->create($this->createApiKeyStruct());

        // return it with secret
        $this->response->status()->setCode(200);
        $this->response->json($generatedKey);
    }

    /**
     * @return ApiKeyStruct
     */
    private function createApiKeyStruct(): ApiKeyStruct
    {
        return new ApiKeyStruct([
            'uid' => $this->getUser()->uid,
            'api_key' => Utils::randomString(20, true),
            'api_secret' => Utils::randomString(20, true),
            'enabled' => true
        ]);
    }

    /**
     * show api key for a logged user
     * api_secret is always hidden
     *
     * There is no need to protect this route
     * @throws NotFoundException
     */
    public function show(): void
    {
        $apiKeyDao = new ApiKeyDao();
        $uid = $this->getUser()->uid ?? throw new NotFoundException('User not authenticated');

        $apiKey = $apiKeyDao->getByUid($uid) ?: throw new NotFoundException('The user has not a valid API key');

        // hide api_secret
        $apiKey->api_secret = '***********';

        $this->response->status()->setCode(200);
        $this->response->json($apiKey);
    }

    /**
     * delete an api key for a logged user
     * @throws NotFoundException
     * @throws Throwable
     */
    public function delete(): void
    {
        (new InternalUserValidator($this))->validate();
        $apiKeyDao = new ApiKeyDao();
        $uid = $this->getUser()->uid ?? throw new NotFoundException('User not authenticated');

        $apiKeyDao->getByUid($uid) ?: throw new NotFoundException('The user has not a valid API key');
        $apiKeyDao->deleteByUid($uid);
    }
}
