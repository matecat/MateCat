<?php

namespace API\App;

use AbstractControllers\AbstractStatefulKleinController;
use API\Commons\Validators\LoginValidator;
use CatUtils;
use Constants_Engines;
use Database;
use Engine;
use EnginesModel_EngineStruct;
use Exception;
use Log;
use ReflectionException;
use TmKeyManagement\UserKeysModel;
use TmKeyManagement_ClientTmKeyStruct;
use TmKeyManagement_Filter;
use TmKeyManagement_MemoryKeyDao;
use TmKeyManagement_MemoryKeyStruct;
use TmKeyManagement_TmKeyStruct;
use Users\MetadataDao;

class TmKeyManagementController extends AbstractStatefulKleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * Return all the keys of the job
     * AND the all keys of the user
     *
     * @throws ReflectionException
     */
    public function getByJob() {

        $idJob    = $this->request->id_job;
        $password = $this->request->password;

        $chunk = CatUtils::getJobFromIdAndAnyPassword( $idJob, $password );

        if ( empty( $chunk ) ) {
            $this->response->status()->setCode( 404 );
            $this->response->json( [
                    'errors' => [
                            'The job was not found'
                    ]
            ] );
            exit();
        }

        $job_keyList = json_decode( $chunk->tm_keys, true );

        if ( !$this->isLoggedIn() ) {

            $tmKeys = [];

            foreach ( $job_keyList as $jobKey ) {
                $jobKey                  = new TmKeyManagement_ClientTmKeyStruct( $jobKey );
                $jobKey->complete_format = true;
                $jobKey->r               = true;
                $jobKey->w               = true;
                $jobKey->owner           = false;
                $tmKeys[]                = $jobKey->hideKey( -1 );
            }

            $this->response->json( [
                    'tm_keys' => $tmKeys
            ] );
            exit();
        }

        if ( CatUtils::isRevisionFromIdJobAndPassword( $idJob, $password ) ) {
            $userRole = TmKeyManagement_Filter::ROLE_REVISOR;
        } elseif ( $this->getUser()->email == $chunk->status_owner ) {
            $userRole = TmKeyManagement_Filter::OWNER;
        } else {
            $userRole = TmKeyManagement_Filter::ROLE_TRANSLATOR;
        }

        $userKeys = new UserKeysModel( $this->getUser(), $userRole );
        $keys     = $userKeys->getKeys( $chunk->tm_keys );

        $this->response->json( [
                'tm_keys' => $this->sortKeysInTheRightOrder( $keys[ 'job_keys' ], $job_keyList )
        ] );
    }

    /**
     * This function sorts the $keys array based on $job_keyList.
     * $keys can contain shared and/or hidden keys
     *
     * @param $keys
     * @param $jobKeyList
     *
     * @return mixed
     */
    private function sortKeysInTheRightOrder( $keys, $jobKeyList ) {
        $sortedKeys = [];

        foreach ( $jobKeyList as $jobKey ) {
            $filter = array_filter( $keys, function ( $key ) use ( $jobKey ) {

                if ( $jobKey[ 'key' ] === $key->key ) {
                    return true;
                }

                // compare only last 5 chars (hidden keys)
                return substr( $jobKey[ 'key' ], -5 ) === substr( $key->key, -5 );
            } );

            if ( !empty( $filter ) ) {
                $sortedKeys[] = array_values( $filter )[ 0 ];
            }
        }

        return $sortedKeys;
    }

    public function getByUserAndKey() {

        try {

            $_keyDao = new TmKeyManagement_MemoryKeyDao( Database::obtain() );
            $dh      = new TmKeyManagement_MemoryKeyStruct( [
                    'uid'    => $this->getUser()->uid,
                    'tm_key' => new TmKeyManagement_TmKeyStruct( [
                                    'key' => $this->request->param( 'key' )
                            ]
                    )
            ] );

            if ( !empty( $_keyDao->read( $dh )[ 0 ] ) ) {
                $this->response->json( $this->_checkForAdaptiveEngines( $dh ) );

                return;
            }

        } catch ( Exception $e ) {
            Log::doJsonLog( $e->getMessage() );
        }

        $this->response->code( 404 );
        $this->response->json( [] );

    }

    /**
     * @param TmKeyManagement_MemoryKeyStruct $memoryKey
     *
     * @return array
     */
    private function _checkForAdaptiveEngines( TmKeyManagement_MemoryKeyStruct $memoryKey ): array {

        // load tmx in engines with adaptivity
        $engineList = Constants_Engines::getAvailableEnginesList();

        $response = [];

        foreach ( $engineList as $engineName ) {

            try {

                $struct             = EnginesModel_EngineStruct::getStruct();
                $struct->class_load = $engineName;
                $struct->type       = Constants_Engines::MT;
                $engine             = Engine::createTempInstance( $struct );

                if ( $engine->isAdaptiveMT() ) {
                    //retrieve OWNER Engine License
                    $ownerMmtEngineMetaData = ( new MetadataDao() )->setCacheTTL( 60 * 60 * 24 * 30 )->get( $this->getUser()->uid, $engine->getEngineRecord()->class_load ); // engine_id
                    if ( !empty( $ownerMmtEngineMetaData ) ) {
                        $engine = Engine::getInstance( $ownerMmtEngineMetaData->value );
                        if ( $engine->getMemoryIfMine( $memoryKey ) ) {
                            $engine_type = explode( "\\", $engine->getEngineRecord()->class_load );
                            $response[]  = array_pop( $engine_type );
                        }
                    }
                }

            } catch ( Exception $e ) {
                if ( $engineName != Constants_Engines::MY_MEMORY ) {
                    Log::doJsonLog( $e->getMessage() );
                }
            }

        }

        return $response;

    }

}