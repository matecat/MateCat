<?php

namespace API\V3;

use API\V2\KleinController;
use API\V2\Validators\LoginValidator;
use ApiKeys_ApiKeyStruct;
use Utils;

class ApiKeyController extends KleinController {

    protected function afterConstruct() {
        parent::afterConstruct();
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function generate() {
        
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
        $values = [
                'uid'        => $this->getUser()->uid,
                'api_key'    => Utils::randomString( 20, true ),
                'api_secret' => Utils::randomString( 20, true ),
                'enabled'    => true
        ];

        return new ApiKeys_ApiKeyStruct( $values );
    }

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

        // hide api secret
        $apiKey->api_secret = '***********';
        
        $this->response->status()->setCode( 200 );
        $this->response->json( $apiKey );
    }

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