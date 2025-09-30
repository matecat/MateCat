<?php

namespace Controller\API\App;

use Controller\Abstracts\AbstractStatefulKleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use Model\DataAccess\Database;
use Model\Engines\Structs\EngineStruct;
use Model\TmKeyManagement\MemoryKeyDao;
use Model\TmKeyManagement\MemoryKeyStruct;
use Model\TmKeyManagement\UserKeysModel;
use Model\Users\MetadataDao;
use ReflectionException;
use Utils\Constants\EngineConstants;
use Utils\Engines\EnginesFactory;
use Utils\Logger\LoggerFactory;
use Utils\TmKeyManagement\ClientTmKeyStruct;
use Utils\TmKeyManagement\Filter;
use Utils\TmKeyManagement\TmKeyStruct;
use Utils\Tools\CatUtils;

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

        $idJob    = $this->request->param( 'id_job' );
        $password = $this->request->param( 'password' );

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
                $jobKey                  = new ClientTmKeyStruct( $jobKey );
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
            $userRole = Filter::ROLE_REVISOR;
        } elseif ( $this->getUser()->email == $chunk->status_owner ) {
            $userRole = Filter::OWNER;
        } else {
            $userRole = Filter::ROLE_TRANSLATOR;
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

            // owner a true solo se sono l'owner del job

        }

        if(!empty($sortedKeys)){
            $sortedKeys = array_map( function ( ClientTmKeyStruct $jobKey ) {
                $jobKey->name = html_entity_decode( $jobKey->name );

                return $jobKey;
            }, $sortedKeys );
        }

        return $sortedKeys;
    }

    /**
     * @throws ReflectionException
     */
    public function getByUserAndKey() {

        $_keyDao = new MemoryKeyDao( Database::obtain() );
        $dh      = new MemoryKeyStruct( [
                'uid'    => $this->getUser()->uid,
                'tm_key' => new TmKeyStruct( [
                                'key' => $this->request->param( 'key' )
                        ]
                )
        ] );

        if ( !empty( $_keyDao->read( $dh )[ 0 ] ) ) {
            $this->response->json( $this->_checkForAdaptiveEngines( $dh ) );

            return;
        }

        $this->response->code( 404 );
        $this->response->json( [] );

    }

    /**
     * @param \Model\TmKeyManagement\MemoryKeyStruct $memoryKey
     *
     * @return array
     */
    private function _checkForAdaptiveEngines( MemoryKeyStruct $memoryKey ): array {

        // load tmx in engines with adaptivity
        $engineList = EngineConstants::getAvailableEnginesList();

        $response = [];

        foreach ( $engineList as $engineName ) {

            try {

                $struct             = EngineStruct::getStruct();
                $struct->class_load = $engineName;
                $struct->type       = EngineConstants::MT;
                $engine             = EnginesFactory::createTempInstance( $struct );

                if ( $engine->isAdaptiveMT() ) {
                    //retrieve OWNER EnginesFactory License
                    $ownerMmtEngineMetaData = ( new MetadataDao() )->setCacheTTL( 60 * 60 * 24 * 30 )->get( $this->getUser()->uid, $engine->getEngineRecord()->class_load ); // engine_id
                    if ( !empty( $ownerMmtEngineMetaData ) ) {
                        $engine = EnginesFactory::getInstance( $ownerMmtEngineMetaData->value );
                        if ( $engine->getMemoryIfMine( $memoryKey ) ) {
                            $engine_type = explode( "\\", $engine->getEngineRecord()->class_load );
                            $response[]  = array_pop( $engine_type );
                        }
                    }
                }

            } catch ( Exception $e ) {
                if ( $engineName != EngineConstants::MY_MEMORY ) {
                    LoggerFactory::getLogger( 'engines' )->debug( $e->getMessage() );
                }
            }

        }

        return $response;

    }

}