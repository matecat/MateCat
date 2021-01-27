<?php

namespace API\App;

use API\V2\KleinController;
use API\V2\Validators\LoginValidator;
use ApiKeys_ApiKeyStruct;
use Utils;

class ApiKeyController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * create an api key for a logged user
     *
     * @throws \Exception
     */
    public function create() {
        
        $apiKeyDao = new \ApiKeys_ApiKeyDao();

        // check if logged user already has a key
        if ( $apiKeyDao->getByUid( $this->getUser()->uid ) ) {
            $this->response->status()->setCode( 500 );
            $this->response->json( [
                    'errors' => [
                            'The user already has a valid key'
                    ]
            ] );
            exit();
        }

        // generate key
        $generatedKey = $apiKeyDao->create( $this->createApiKeyStruct() );

        // return it with secret
        $this->response->status()->setCode( 200 );
        $this->response->json( $generatedKey );
    }

    /**
     * @return ApiKeys_ApiKeyStruct
     */
    private function createApiKeyStruct() {

        return new ApiKeys_ApiKeyStruct( [
                'uid'        => $this->getUser()->uid,
                'api_key'    => Utils::randomString( 20, true ),
                'api_secret' => Utils::randomString( 20, true ),
                'enabled'    => true
        ] );
    }

    /**
     * show api key for a logged user
     * api_secret is always hidden
     */
    public function show() {

        $apiKeyDao = new \ApiKeys_ApiKeyDao();

        if ( !$apiKey = $apiKeyDao->getByUid( $this->getUser()->uid ) ) {
            $this->response->status()->setCode( 404 );
            $this->response->json( [
                    'errors' => [
                            'The user has not a valid API key'
                    ]
            ] );
            exit();
        }

        // hide api_secret
        $apiKey->api_secret = '***********';
        
        $this->response->status()->setCode( 200 );
        $this->response->json( $apiKey );
    }

    /**
     * delete an api key for a logged user
     */
    public function delete() {

        $apiKeyDao = new \ApiKeys_ApiKeyDao();

        if ( !$apiKey = $apiKeyDao->getByUid( $this->getUser()->uid ) ) {
            $this->response->status()->setCode( 404 );
            $this->response->json( [
                    'errors' => [
                            'The user has not a valid API key'
                    ]
            ] );
            exit();
        }

        try {
            $apiKeyDao->deleteByUid( $this->getUser()->uid );

            $this->response->status()->setCode( 200 );
            $this->response->json( [
                    'message' => [
                            'success'
                    ]
            ] );
            
        } catch ( \Exception $e ) {
            $this->response->status()->setCode( 500 );
            $this->response->json( [
                    'errors' => [
                            $e->getMessage()
                    ]
            ] );
        }
    }
}