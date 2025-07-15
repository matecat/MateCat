<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use DomainException;
use INIT;
use Model\TmKeyManagement\UserKeysModel;
use ReflectionException;
use Swaggest\JsonSchema\InvalidValue;
use Utils\ActiveMQ\WorkerClient;
use Utils\AsyncTasks\Workers\GlossaryWorker;
use Utils\Langs\Languages;
use Utils\Logger\Log;
use Utils\TmKeyManagement\Filter;
use Utils\Tools\CatUtils;
use Utils\Tools\Utils;
use Utils\Validator\JSONSchema\JSONValidator;
use Utils\Validator\JSONSchema\JSONValidatorObject;

class GlossaryController extends KleinController {

    const GLOSSARY_WRITE = 'GLOSSARY_WRITE';
    const GLOSSARY_READ  = 'GLOSSARY_READ';

    /**
     * @return array
     */
    private function responseOk() {
        return [
                'success' => true
        ];
    }

    /**
     * Glossary check action
     *
     * @throws InvalidValue
     * @throws ReflectionException
     */
    public function check() {
        $jsonSchemaPath   = INIT::$ROOT . '/inc/validation/schema/glossary/check.json';
        $json             = $this->createThePayloadForWorker( $jsonSchemaPath );
        $json[ 'tmKeys' ] = $this->keysBelongingToJobOwner( $json[ 'tmKeys' ] );

        // Don't use the keys sent by the FE
        $tmKeys = $json[ 'tmKeys' ];
        $keys   = [];

        foreach ( $tmKeys as $tmKey ) {
            $keys[] = $tmKey[ 'key' ];
        }

        $json[ 'keys' ] = $keys;

        $params = [
                'action'  => 'check',
                'payload' => $json,
        ];

        $this->enqueueWorker( self::GLOSSARY_READ, $params );

        $this->response->json( $this->responseOk() );
    }

    /**
     * Delete action on Match
     *
     * @throws ReflectionException
     * @throws InvalidValue
     */
    public function delete() {
        $jsonSchemaPath = INIT::$ROOT . '/inc/validation/schema/glossary/delete.json';
        $json           = $this->createThePayloadForWorker( $jsonSchemaPath );

        $this->checkWritePermissions( [ $json[ 'term' ][ 'metadata' ][ 'key' ] ], $json[ 'userKeys' ] );

        $params = [
                'action'  => 'delete',
                'payload' => $json,
        ];

        $this->enqueueWorker( self::GLOSSARY_WRITE, $params );

        $this->response->json( $this->responseOk() );
    }

    /**
     * Get the domains from Match
     *
     * @throws ReflectionException
     * @throws InvalidValue
     */
    public function domains() {
        $jsonSchemaPath = INIT::$ROOT . '/inc/validation/schema/glossary/domains.json';
        $json           = $this->createThePayloadForWorker( $jsonSchemaPath );

        $params = [
                'action'  => 'domains',
                'payload' => $json,
        ];

        $this->enqueueWorker( self::GLOSSARY_READ, $params );

        $this->response->json( $this->responseOk() );
    }

    /**
     * Get action on Match
     *
     * @throws ReflectionException
     * @throws InvalidValue
     */
    public function get() {
        $jsonSchemaPath   = INIT::$ROOT . '/inc/validation/schema/glossary/get.json';
        $json             = $this->createThePayloadForWorker( $jsonSchemaPath );
        $json[ 'tmKeys' ] = $this->keysBelongingToJobOwner( $json[ 'tmKeys' ] );

        $params = [
                'action'  => 'get',
                'payload' => $json,
        ];

        $this->enqueueWorker( self::GLOSSARY_READ, $params );

        $this->response->json( $this->responseOk() );
    }

    /**
     * Retrieve from Match the information if keys have at least one glossary associated
     *
     * @throws ReflectionException
     * @throws InvalidValue
     */
    public function keys() {
        $jsonSchemaPath = INIT::$ROOT . '/inc/validation/schema/glossary/keys.json';
        $json           = $this->createThePayloadForWorker( $jsonSchemaPath );
        $keysArray      = [];

        foreach ( $json[ 'tmKeys' ] as $key ) {
            $keysArray[] = $key[ 'key' ];
        }

        $json[ 'keys' ] = $keysArray;

        $params = [
                'action'  => 'keys',
                'payload' => $json,
        ];

        $this->enqueueWorker( self::GLOSSARY_READ, $params );

        $this->response->json( $this->responseOk() );
    }

    /**
     * Search for a specific sentence in Match
     *
     * @throws ReflectionException
     * @throws InvalidValue
     */
    public function search() {
        $jsonSchemaPath   = INIT::$ROOT . '/inc/validation/schema/glossary/search.json';
        $json             = $this->createThePayloadForWorker( $jsonSchemaPath );
        $json[ 'tmKeys' ] = $this->keysBelongingToJobOwner( $json[ 'tmKeys' ] );

        $params = [
                'action'  => 'search',
                'payload' => $json,
        ];

        $this->enqueueWorker( self::GLOSSARY_READ, $params );

        $this->response->json( $this->responseOk() );
    }

    /**
     * Set action on Match
     *
     * @throws ReflectionException
     * @throws InvalidValue
     */
    public function set() {
        $jsonSchemaPath = INIT::$ROOT . '/inc/validation/schema/glossary/set.json';
        $json           = $this->createThePayloadForWorker( $jsonSchemaPath );

        $keys = [];
        foreach ( $json[ 'term' ][ 'metadata' ][ 'keys' ] as $key ) {
            $keys[] = $key[ 'key' ];
        }

        $this->checkWritePermissions( $keys, $json[ 'userKeys' ] );

        $params = [
                'action'  => 'set',
                'payload' => $json,
        ];

        $this->enqueueWorker( self::GLOSSARY_WRITE, $params );

        $this->response->json( $this->responseOk() );
    }

    /**
     * Update action on Match
     *
     * @throws ReflectionException
     * @throws InvalidValue
     */
    public function update() {
        $jsonSchemaPath = INIT::$ROOT . '/inc/validation/schema/glossary/update.json';
        $json           = $this->createThePayloadForWorker( $jsonSchemaPath );

        $this->checkWritePermissions( [ $json[ 'term' ][ 'metadata' ][ 'key' ] ], $json[ 'userKeys' ] );

        $params = [
                'action'  => 'update',
                'payload' => $json,
        ];

        $this->enqueueWorker( self::GLOSSARY_WRITE, $params );

        $this->response->json( $this->responseOk() );
    }

    /**
     * This function validates the payload
     * and returns an object ready for GlossaryWorker
     *
     * @param $jsonSchemaPath
     *
     * @return array
     * @throws ReflectionException
     * @throws InvalidValue
     */
    private function createThePayloadForWorker( $jsonSchemaPath ): array {
        $jsonSchema = file_get_contents( $jsonSchemaPath );
        $this->validateJson( $this->request->body(), $jsonSchema );

        $json = json_decode( $this->request->body(), true );

        if ( isset( $json[ 'target_language' ] ) and isset( $json[ 'source_language' ] ) ) {
            $this->validateLanguage( $json[ 'target_language' ] );
            $this->validateLanguage( $json[ 'source_language' ] );

            // handle source and target
            if ( isset( $json[ 'source' ] ) ) {
                $json[ 'source' ] = html_entity_decode( $json[ 'source' ] );
            }

            if ( isset( $json[ 'target' ] ) ) {
                $json[ 'target' ] = html_entity_decode( $json[ 'target' ] );
            }
        }

        if ( isset( $json[ 'term' ][ 'target_language' ] ) and isset( $json[ 'term' ][ 'source_language' ] ) ) {
            $this->validateLanguage( $json[ 'term' ][ 'target_language' ] );
            $this->validateLanguage( $json[ 'term' ][ 'source_language' ] );
        }

        $job = CatUtils::getJobFromIdAndAnyPassword( $json[ 'id_job' ], $json[ 'password' ] );

        if ( $job === null ) {
            throw new DomainException( 'Wrong id_job/password combination' );
        }

        $json[ 'id_segment' ] = ( isset( $json[ 'id_segment' ] ) ) ? $json[ 'id_segment' ] : null;
        $json[ 'jobData' ]    = $job->toArray();
        $json[ 'tmKeys' ]     = json_decode( $job->tm_keys, true );
        $json[ 'userKeys' ]   = [];

        // Add user keys
        if ( $this->isLoggedIn() ) {

            if ( CatUtils::isRevisionFromIdJobAndPassword( $json[ 'id_job' ], $json[ 'password' ] ) ) {
                $userRole = Filter::ROLE_REVISOR;
            } elseif ( $this->user->email == $job->status_owner ) {
                $userRole = Filter::OWNER;
            } else {
                $userRole = Filter::ROLE_TRANSLATOR;
            }

            $userKeys = new UserKeysModel( $this->user, $userRole );

            $json[ 'userKeys' ] = $userKeys->getKeys( $job->tm_keys )[ 'job_keys' ];
        }

        return $json;
    }

    /**
     * @param $tmKeys
     *
     * @return array
     */
    private function keysBelongingToJobOwner( $tmKeys ) {
        $return = [];

        foreach ( $tmKeys as $tmKey ) {

            // allowing only user terms with read permission
            if ( isset( $tmKey[ 'r' ] ) and $tmKey[ 'r' ] == 1 ) {

                // allowing only terms belonging to the owner of the job
                if ( isset( $tmKey[ 'owner' ] ) and $tmKey[ 'owner' ] == true ) {
                    $return[] = $tmKey;
                }
            }

            // additional terms are also visible for the other users (NOT the owner of the job) who added them
            if (
                    $this->isLoggedIn() and
                    ( $this->user->uid == $tmKey[ 'uid_transl' ] and $tmKey[ 'r_transl' ] == true ) or
                    ( $this->user->uid == $tmKey[ 'uid_rev' ] and $tmKey[ 'r_rev' ] == true )
            ) {
                $return[] = $tmKey;
            }
        }

        return $return;
    }

    /**
     * @param array                                      $keys
     * @param \Utils\TmKeyManagement\ClientTmKeyStruct[] $userKeys
     */
    private function checkWritePermissions( array $keys, array $userKeys ) {
        $allowedKeys = [];

        foreach ( $userKeys as $userKey ) {
            $allowedKeys[] = $userKey->key;
        }

        // loop $keys
        foreach ( $keys as $key ) {

            // check if $key is allowed
            if ( !in_array( $key, $allowedKeys ) ) {
                $this->response->code( 500 );
                $this->response->json( [
                        'error' => "Key " . $key . " does not belong to this job"
                ] );
                die();
            }

            // check key permissions
            $keyIsUse = array_filter( $userKeys, function ( Utils\TmKeyManagement\ClientTmKeyStruct $userKey ) use ( $key ) {
                return $userKey->key === $key;
            } )[ 0 ];

            // is a glossary key?
            if ( $keyIsUse->glos === false ) {
                $this->response->code( 500 );
                $this->response->json( [
                        'error' => "Key " . $key . " is not a glossary key"
                ] );
                die();
            }

            // write permissions?
            if ( $keyIsUse->edit === false or $keyIsUse->w === 0 ) {
                $this->response->code( 500 );
                $this->response->json( [
                        'error' => "Key " . $key . " has not write permissions"
                ] );
                die();
            }
        }
    }

    /**
     * @param $json
     * @param $jsonSchema
     *
     * @throws InvalidValue
     * @throws \Swaggest\JsonSchema\Exception
     * @throws \Utils\Validator\JSONSchema\Errors\JSONValidatorException
     * @throws \Utils\Validator\JSONSchema\Errors\JsonValidatorGenericException
     */
    private function validateJson( $json, $jsonSchema ) {
        $validatorObject       = new JSONValidatorObject();
        $validatorObject->json = $json;

        $validator = new JSONValidator( $jsonSchema );
        $validator->validate( $validatorObject );

        if ( !$validator->isValid() ) {

            $error = $validator->getExceptions()[ 0 ]->error;

            $this->response->code( 400 );
            $this->response->json( [
                    'error' => $error->getMessage()
            ] );
            die();
        }
    }

    /**
     * @param $language
     */
    private function validateLanguage( $language ) {
        $languages = Languages::getInstance();
        if ( !$languages->isValidLanguage( $language ) ) {
            $this->response->code( 500 );
            $this->response->json( [
                    'error' => $language . ' is not an allowed language'
            ] );
            die();
        }
    }

    /**
     * Enqueue a Worker
     *
     * @param $queue
     * @param $params
     */
    private function enqueueWorker( $queue, $params ) {
        try {
            WorkerClient::enqueue( $queue, GlossaryWorker::class, $params, [ 'persistent' => WorkerClient::$_HANDLER->persistent ] );
        } catch ( \Exception $e ) {
            # Handle the error, logging, ...
            $output = "**** Glossary enqueue request failed. AMQ Connection Error. ****\n\t";
            $output .= "{$e->getMessage()}";
            $output .= var_export( $params, true );
            Log::doJsonLog( $output );
            Utils::sendErrMailReport( $output );
        }
    }
}
