<?php

namespace API\V3;

use API\V2\KleinController;
use API\V2\Validators\LoginValidator;
use TmKeyManagement_MemoryKeyDao;
use TmKeyManagement_MemoryKeyStruct;

class TmKeyManagementController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * Return all the keys of the user
     */
    public function getByUser(){

        try {
            $_keyDao = new TmKeyManagement_MemoryKeyDao( \Database::obtain() );
            $dh      = new TmKeyManagement_MemoryKeyStruct( array( 'uid' => $this->user->uid ) );
            $keyList = $_keyDao->read( $dh );

            $list = ['tm_keys' => []];
            foreach ($keyList as $key){
                $list['tm_keys'][] = $key->tm_key;
            }

            $this->response->json( $list );
            exit();

        } catch (\Exception $exception){
            $this->response->status()->setCode( 500 );
            $this->response->json( [
                'errors' => [
                    $exception->getMessage()
                ]
            ] );
            exit();
        }
    }
}