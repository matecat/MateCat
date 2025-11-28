<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthorizationError;
use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\Commons\Exceptions\ValidationError;
use DomainException;
use Exception;
use Model\TmKeyManagement\UserKeysModel;
use ReflectionException;
use Swaggest\JsonSchema\Exception as JsonSchemaException;
use Swaggest\JsonSchema\InvalidValue;
use Utils\ActiveMQ\WorkerClient;
use Utils\AsyncTasks\Workers\GlossaryWorker;
use Utils\Langs\Languages;
use Utils\TmKeyManagement\ClientTmKeyStruct;
use Utils\TmKeyManagement\Filter;
use Utils\Tools\CatUtils;
use Utils\Validator\Contracts\ValidatorObject;
use Utils\Validator\JSONSchema\Errors\JSONValidatorException;
use Utils\Validator\JSONSchema\Errors\JsonValidatorGenericException;
use Utils\Validator\JSONSchema\JSONValidator;
use Utils\Validator\JSONSchema\JSONValidatorObject;

class GlossaryController extends KleinController
{

    const string GLOSSARY_WRITE = 'GLOSSARY_WRITE';
    const string GLOSSARY_READ  = 'GLOSSARY_READ';

    /**
     * @return array
     */
    private function responseOk(): array
    {
        return [
                'success' => true
        ];
    }

    /**
     * Glossary check action
     *
     * @throws JsonSchemaException
     * @throws InvalidValue
     * @throws JSONValidatorException
     * @throws JsonValidatorGenericException
     * @throws ReflectionException
     * @throws Exception
     */
    public function check(): void
    {
        $json             = $this->createThePayloadForWorker('check.json');
        $json[ 'tmKeys' ] = $this->keysBelongingToJobOwner($json[ 'tmKeys' ]);

        // Don't use the keys sent by the FE
        $tmKeys = $json[ 'tmKeys' ];
        $keys   = [];

        foreach ($tmKeys as $tmKey) {
            $keys[] = $tmKey[ 'key' ];
        }

        $json[ 'keys' ] = $keys;

        $params = [
                'action'  => 'check',
                'payload' => $json,
        ];

        $this->enqueueWorker(self::GLOSSARY_READ, $params);

        $this->response->json($this->responseOk());
    }

    /**
     * Delete action on MyMemory
     *
     * @throws JsonSchemaException
     * @throws InvalidValue
     * @throws JSONValidatorException
     * @throws JsonValidatorGenericException
     * @throws ReflectionException
     * @throws Exception
     */
    public function delete(): void
    {
        $json = $this->createThePayloadForWorker('delete.json');

        $this->checkWritePermissions([$json[ 'term' ][ 'metadata' ][ 'key' ]], $json[ 'userKeys' ]);

        $params = [
                'action'  => 'delete',
                'payload' => $json,
        ];

        $this->enqueueWorker(self::GLOSSARY_WRITE, $params);

        $this->response->json($this->responseOk());
    }

    /**
     * Get the domains from MyMemory
     *
     * @throws JsonSchemaException
     * @throws InvalidValue
     * @throws JSONValidatorException
     * @throws JsonValidatorGenericException
     * @throws ReflectionException
     * @throws Exception
     */
    public function domains(): void
    {
        $json = $this->createThePayloadForWorker('domains.json');

        $params = [
                'action'  => 'domains',
                'payload' => $json,
        ];

        $this->enqueueWorker(self::GLOSSARY_READ, $params);

        $this->response->json($this->responseOk());
    }

    /**
     * Get action on MyMemory
     *
     * @throws JsonSchemaException
     * @throws InvalidValue
     * @throws JSONValidatorException
     * @throws JsonValidatorGenericException
     * @throws ReflectionException
     * @throws Exception
     */
    public function get(): void
    {
        $json             = $this->createThePayloadForWorker('get.json');
        $json[ 'tmKeys' ] = $this->keysBelongingToJobOwner($json[ 'tmKeys' ]);

        $params = [
                'action'  => 'get',
                'payload' => $json,
        ];

        $this->enqueueWorker(self::GLOSSARY_READ, $params);

        $this->response->json($this->responseOk());
    }

    /**
     * Retrieve from MyMemory the information if keys have at least one glossary associated
     *
     * @throws JsonSchemaException
     * @throws InvalidValue
     * @throws JSONValidatorException
     * @throws JsonValidatorGenericException
     * @throws ReflectionException
     * @throws Exception
     */
    public function keys(): void
    {
        $json      = $this->createThePayloadForWorker('keys.json');
        $keysArray = [];

        foreach ($json[ 'tmKeys' ] as $key) {
            $keysArray[] = $key[ 'key' ];
        }

        $json[ 'keys' ] = $keysArray;

        $params = [
                'action'  => 'keys',
                'payload' => $json,
        ];

        $this->enqueueWorker(self::GLOSSARY_READ, $params);

        $this->response->json($this->responseOk());
    }

    /**
     * Search for a specific sentence in MyMemory
     *
     * @throws JsonSchemaException
     * @throws InvalidValue
     * @throws JSONValidatorException
     * @throws JsonValidatorGenericException
     * @throws ReflectionException
     * @throws Exception
     */
    public function search(): void
    {
        $json             = $this->createThePayloadForWorker('search.json');
        $json[ 'tmKeys' ] = $this->keysBelongingToJobOwner($json[ 'tmKeys' ]);

        $params = [
                'action'  => 'search',
                'payload' => $json,
        ];

        $this->enqueueWorker(self::GLOSSARY_READ, $params);

        $this->response->json($this->responseOk());
    }

    /**
     * Set action on MyMemory
     *
     * @throws JsonSchemaException
     * @throws InvalidValue
     * @throws JSONValidatorException
     * @throws JsonValidatorGenericException
     * @throws ReflectionException
     * @throws Exception
     */
    public function set(): void
    {
        $json = $this->createThePayloadForWorker('set.json');

        $keys = [];
        foreach ($json[ 'term' ][ 'metadata' ][ 'keys' ] as $key) {
            $keys[] = $key[ 'key' ];
        }

        $this->checkWritePermissions($keys, $json[ 'userKeys' ]);

        $params = [
                'action'  => 'set',
                'payload' => $json,
        ];

        $this->enqueueWorker(self::GLOSSARY_WRITE, $params);

        $this->response->json($this->responseOk());
    }

    /**
     * Update action on MyMemory
     *
     * @throws JsonSchemaException
     * @throws InvalidValue
     * @throws JSONValidatorException
     * @throws JsonValidatorGenericException
     * @throws ReflectionException
     * @throws Exception
     */
    public function update(): void
    {
        $json = $this->createThePayloadForWorker('update.json');

        $this->checkWritePermissions([$json[ 'term' ][ 'metadata' ][ 'key' ]], $json[ 'userKeys' ]);

        $params = [
                'action'  => 'update',
                'payload' => $json,
        ];

        $this->enqueueWorker(self::GLOSSARY_WRITE, $params);

        $this->response->json($this->responseOk());
    }

    /**
     * This function validates the payload
     * and returns an object ready for GlossaryWorker
     *
     * @param $jsonSchemaPath
     *
     * @return array
     * @throws InvalidValue
     * @throws ReflectionException
     * @throws JsonSchemaException
     * @throws JSONValidatorException
     * @throws JsonValidatorGenericException
     * @throws ValidationError
     * @throws Exception
     */
    private function createThePayloadForWorker($jsonSchemaPath): array
    {
        $json = $this->validateJson($this->request->body(), $jsonSchemaPath)->getValue(true);

        if (isset($json[ 'target_language' ]) and isset($json[ 'source_language' ])) {
            $this->validateLanguage($json[ 'target_language' ]);
            $this->validateLanguage($json[ 'source_language' ]);

            // handle source and target
            if (isset($json[ 'source' ])) {
                $json[ 'source' ] = html_entity_decode($json[ 'source' ]);
            }

            if (isset($json[ 'target' ])) {
                $json[ 'target' ] = html_entity_decode($json[ 'target' ]);
            }
        }

        if (isset($json[ 'term' ][ 'target_language' ]) and isset($json[ 'term' ][ 'source_language' ])) {
            $this->validateLanguage($json[ 'term' ][ 'target_language' ]);
            $this->validateLanguage($json[ 'term' ][ 'source_language' ]);
        }

        $job = CatUtils::getJobFromIdAndAnyPassword($json[ 'id_job' ], $json[ 'password' ]);

        if ($job === null) {
            throw new DomainException('Wrong id_job/password combination');
        }

        $json[ 'id_segment' ] = (isset($json[ 'id_segment' ])) ? $json[ 'id_segment' ] : null;
        $json[ 'jobData' ]    = $job->toArray();
        $json[ 'tmKeys' ]     = json_decode($job->tm_keys, true);
        $json[ 'userKeys' ]   = [];

        // Add user keys
        if ($this->isLoggedIn()) {
            if (CatUtils::isRevisionFromIdJobAndPassword($json[ 'id_job' ], $json[ 'password' ])) {
                $userRole = Filter::ROLE_REVISOR;
            } elseif ($this->user->email == $job->status_owner) {
                $userRole = Filter::OWNER;
            } else {
                $userRole = Filter::ROLE_TRANSLATOR;
            }

            $userKeys = new UserKeysModel($this->user, $userRole);

            $json[ 'userKeys' ] = $userKeys->getKeys($job->tm_keys)[ 'job_keys' ];
        }

        return $json;
    }

    /**
     * @param $tmKeys
     *
     * @return array
     */
    private function keysBelongingToJobOwner($tmKeys): array
    {
        $return = [];

        foreach ($tmKeys as $tmKey) {
            // allowing only user terms with read permission
            if (isset($tmKey[ 'r' ]) and $tmKey[ 'r' ] == 1) {
                // allowing only terms belonging to the owner of the job
                if (isset($tmKey[ 'owner' ]) and $tmKey[ 'owner' ]) {
                    $return[] = $tmKey;
                }
            }

            // additional terms are also visible for the other users (NOT the owner of the job) who added them
            if (
                    $this->isLoggedIn() and
                    ($this->user->uid == $tmKey[ 'uid_transl' ] and $tmKey[ 'r_transl' ]) or
                    ($this->user->uid == $tmKey[ 'uid_rev' ] and $tmKey[ 'r_rev' ])
            ) {
                $return[] = $tmKey;
            }
        }

        return $return;
    }

    /**
     * @param array               $keys
     * @param ClientTmKeyStruct[] $userKeys
     *
     * @throws Exception
     */
    private function checkWritePermissions(array $keys, array $userKeys): void
    {
        $allowedKeys = [];

        foreach ($userKeys as $userKey) {
            $allowedKeys[] = $userKey->key;
        }

        // loop $keys
        foreach ($keys as $key) {
            // check if $key is allowed
            if (!in_array($key, $allowedKeys)) {
                throw new NotFoundException("Key " . $key . " does not belong to this job");
            }

            // check key permissions
            $keyIsUse = array_values(
                    array_filter($userKeys, function (ClientTmKeyStruct $userKey) use ($key) {
                        return $userKey->key === $key;
                    })
            )[ 0 ];

            // is a glossary key?
            if ($keyIsUse->glos === false) {
                throw new NotFoundException("Key " . $key . " is not a glossary key");
            }

            // write permissions?
            if ($keyIsUse->edit === false || empty($keyIsUse->w)) {
                throw new AuthorizationError("Key " . $key . " has not write permissions");
            }
        }
    }

    /**
     * @param $json
     * @param $jsonSchema
     *
     * @return JSONValidatorObject|null
     * @throws InvalidValue
     * @throws JSONValidatorException
     * @throws JsonSchemaException
     * @throws JsonValidatorGenericException
     */
    private function validateJson($json, $jsonSchema): ?ValidatorObject
    {
        $validatorObject = new JSONValidatorObject($json);
        $validator       = new JSONValidator('glossary/' . $jsonSchema, true);

        return $validator->validate($validatorObject);
    }

    /**
     * @param $language
     *
     * @throws ValidationError
     */
    private function validateLanguage($language): void
    {
        $languages = Languages::getInstance();
        if (!$languages->isValidLanguage($language)) {
            throw new ValidationError($language . ' is not a valid language');
        }
    }

    /**
     * Enqueue a Worker
     *
     * @param $queue
     * @param $params
     *
     * @throws Exception
     */
    private function enqueueWorker($queue, $params): void
    {
        WorkerClient::enqueue($queue, GlossaryWorker::class, $params, ['persistent' => WorkerClient::$_HANDLER->persistent]);
    }
}
