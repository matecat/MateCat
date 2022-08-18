<?php

namespace API\App;

use API\V2\Validators\LoginValidator;
use CatUtils;
use TmKeyManagement\UserKeysModel;
use TmKeyManagement_Filter;
use TmKeyManagement_MemoryKeyDao;
use TmKeyManagement_MemoryKeyStruct;

class TmKeyManagementController extends AbstractStatefulKleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * Return all the keys of the job
     * AND the all keys of the user
     */
    public function getByJob(){

        $idJob = $this->request->id_job;
        $password = $this->request->password;

        $chunk = \CatUtils::getJobFromIdAndAnyPassword($idJob, $password);

        if(empty($chunk)){
            $this->response->status()->setCode( 404 );
            $this->response->json( [
                    'errors' => [
                            'The job was not found'
                    ]
            ] );
            exit();
        }

        if ( $this->isJobRevision($idJob, $password) ) {
            $userRole = TmKeyManagement_Filter::ROLE_REVISOR;
        } elseif ( $this->user->email == $chunk->status_owner ) {
            $userRole = TmKeyManagement_Filter::OWNER;
        } else {
            $userRole = TmKeyManagement_Filter::ROLE_TRANSLATOR;
        }

        $userKeys = new UserKeysModel($this->user, $userRole ) ;

        $this->response->json( $userKeys->getKeys( $chunk->tm_keys ) );
    }

    /**
     * @param $idJob
     * @param $password
     *
     * @return bool|null
     */
    private function isJobRevision($idJob, $password) {
        return CatUtils::getIsRevisionFromIdJobAndPassword( $idJob, $password );
    }

    /**
     * Return all the keys of the user
     */
    public function getByUser(){

        try {
            $_keyDao = new TmKeyManagement_MemoryKeyDao( \Database::obtain() );
            $dh      = new TmKeyManagement_MemoryKeyStruct( array( 'uid' => $this->user->uid ) );
            $keyList = $_keyDao->read( $dh );

            $list = [];
            foreach ($keyList as $key){
                $list[] = $key->tm_key;
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