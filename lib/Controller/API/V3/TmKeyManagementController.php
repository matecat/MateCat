<?php

namespace Controller\API\V3;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use Model\DataAccess\Database;
use Model\TmKeyManagement\MemoryKeyDao;
use Model\TmKeyManagement\MemoryKeyStruct;

class TmKeyManagementController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * Return all the keys of the user
     */
    public function getByUser() {

        try {
            $_keyDao = new MemoryKeyDao( Database::obtain() );
            $dh      = new MemoryKeyStruct( [ 'uid' => $this->user->uid ] );
            $keyList = $_keyDao->read( $dh );

            $list = [ 'tm_keys' => [] ];
            foreach ( $keyList as $key ) {
                $list[ 'tm_keys' ][] = $key->tm_key;
            }

            $this->response->json( $list );
            exit();

        } catch ( Exception $exception ) {
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