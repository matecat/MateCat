<?php

namespace API\App;

use API\V2\Validators\LoginValidator;
use CatUtils;
use Engines_MyMemory;
use EnginesModel_EngineStruct;
use TmKeyManagement\UserKeysModel;
use TmKeyManagement_ClientTmKeyStruct;
use TmKeyManagement_Filter;
use TmKeyManagement_MemoryKeyDao;
use TmKeyManagement_MemoryKeyStruct;

class TmKeyManagementController extends AbstractStatefulKleinController {

    /**
     * @throws \Exception
     */
    public function getByUser()
    {
        if(!$this->userIsLogged()){
            $this->response->json( [
                'tm_keys' => []
            ] );
            exit();
        }

        $_keyDao = new TmKeyManagement_MemoryKeyDao( \Database::obtain() );
        $dh      = new TmKeyManagement_MemoryKeyStruct( ['uid' => $this->user->uid] );
        $keyList = $_keyDao->read( $dh );

        $keys = [];
        $keyIds = [];

        foreach ($keyList as $key){
            $keyIds[] = $key->tm_key->key;
            $keys[] = [
                'id' =>  $key->tm_key->key,
                'name' =>  $key->tm_key->name,
                'has_glossary' => false,
            ];
        }

        $myMemoryClient = $this->getMyMemoryClient();
        $hasGlossaryMap = $myMemoryClient->glossaryKeys(null ,null, $keyIds);

        // update `has_glossary` in $keys
        foreach ($keys as $index => $key) {
            if(isset($hasGlossaryMap->entries[$key['id']])){
                $keys[$index]['has_glossary'] = $hasGlossaryMap->entries[$key['id']];
            }
        }

        $this->response->json( $keys );
        exit();
    }

    /**
     * @return Engines_MyMemory
     * @throws \Exception
     */
    private function getMyMemoryClient()
    {
        $engineDAO        = new \EnginesModel_EngineDAO( \Database::obtain() );
        $engineStruct     = \EnginesModel_EngineStruct::getStruct();
        $engineStruct->id = 1;

        $eng = $engineDAO->setCacheTTL( 60 * 5 )->read( $engineStruct );

        /**
         * @var $engineRecord EnginesModel_EngineStruct
         */
        $engineRecord = @$eng[ 0 ];

        return new Engines_MyMemory( $engineRecord );
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

        if(!$this->userIsLogged()){

            $tmKeys = [];
            $job_keyList = json_decode( $chunk->tm_keys, true );

            foreach ( $job_keyList as $jobKey ) {
                $jobKey = new TmKeyManagement_ClientTmKeyStruct( $jobKey );
                $jobKey->complete_format = true;
                $jobKey->r = true;
                $jobKey->w = true;
                $jobKey->owner = false;
                $tmKeys[] = $jobKey->hideKey( -1 );
            }

            $this->response->json( [
                'tm_keys' => $tmKeys
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
        $keys = $userKeys->getKeys( $chunk->tm_keys );

        $this->response->json( [
            'tm_keys' => $keys['job_keys']
        ] );
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
}