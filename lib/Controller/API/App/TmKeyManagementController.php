<?php

namespace API\App;

use CatUtils;
use TmKeyManagement\UserKeysModel;
use TmKeyManagement_ClientTmKeyStruct;
use TmKeyManagement_Filter;

class TmKeyManagementController extends AbstractStatefulKleinController {

    /**
     * Return all the keys of the job
     * AND the all keys of the user
     */
    public function getByJob(){

        $idJob = $this->request->id_job;
        $password = $this->request->password;

        $chunk = CatUtils::getJobFromIdAndAnyPassword($idJob, $password);

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