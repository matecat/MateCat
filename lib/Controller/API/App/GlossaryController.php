<?php

namespace API\App;

use API\V2\KleinController;
use API\V2\Validators\LoginValidator;
use TmKeyManagement\UserKeysModel;
use TmKeyManagement_Filter;
use Validator\JSONValidatorObject;
use Engines_MyMemory;

class GlossaryController extends KleinController {

    const GLOSSARY_WRITE = 'GLOSSARY_WRITE';
    const GLOSSARY_READ  = 'GLOSSARY_READ';

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * Glossary check action
     *
     * @throws \Swaggest\JsonSchema\InvalidValue
     * @throws \ReflectionException
     */
    public function check()
    {
        $jsonSchemaPath =  __DIR__ . '/../../../../inc/validation/schema/glossary/check.json';
        $json = $this->createThePayloadForWorker($jsonSchemaPath);

        $params = [
            'action' => 'check',
            'payload' => $json,
        ];

        $this->enqueueWorker( self::GLOSSARY_READ, $params );

        $this->response->json($json);
    }

    /**
     * Delete action on MyMemory
     *
     * @throws \ReflectionException
     * @throws \Swaggest\JsonSchema\InvalidValue
     */
    public function delete()
    {
        $jsonSchemaPath =  __DIR__ . '/../../../../inc/validation/schema/glossary/delete.json';
        $json = $this->createThePayloadForWorker($jsonSchemaPath);

        $this->checkWritePermissions([$json['term']['metadata']['key']], $json['userKeys']);

        $params = [
                'action' => 'delete',
                'payload' => $json,
        ];

        $this->enqueueWorker( self::GLOSSARY_WRITE, $params );

        $this->response->json($json);
    }

    /**
     * Get the domains from MyMemory
     *
     * @throws \ReflectionException
     * @throws \Swaggest\JsonSchema\InvalidValue
     */
    public function domains()
    {
        $jsonSchemaPath =  __DIR__ . '/../../../../inc/validation/schema/glossary/domains.json' ;
        $json = $this->createThePayloadForWorker($jsonSchemaPath);

        $params = [
            'action' => 'domains',
            'payload' => $json,
        ];

        $this->enqueueWorker( self::GLOSSARY_READ, $params );

        $this->response->json($json);
    }

    /**
     * @TODO REMOVE
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
     * Get action on MyMemory
     *
     * @throws \ReflectionException
     * @throws \Swaggest\JsonSchema\InvalidValue
     */
    public function get()
    {
        $jsonSchemaPath =  __DIR__ . '/../../../../inc/validation/schema/glossary/get.json' ;
        $json = $this->createThePayloadForWorker($jsonSchemaPath);

        $params = [
            'action' => 'get',
            'payload' => $json,
        ];

        $this->enqueueWorker( self::GLOSSARY_READ, $params );

        $this->response->json($json);
    }

    /**
     * Search for a specific sentence in MyMemory
     *
     * @throws \ReflectionException
     * @throws \Swaggest\JsonSchema\InvalidValue
     */
    public function search()
    {
        $jsonSchemaPath =  __DIR__ . '/../../../../inc/validation/schema/glossary/search.json' ;
        $json = $this->createThePayloadForWorker($jsonSchemaPath);

        $params = [
                'action' => 'search',
                'payload' => $json,
        ];

        $this->enqueueWorker( self::GLOSSARY_READ, $params );

        $this->response->json($json);
    }

    /**
     * Set action on MyMemory
     *
     * @throws \ReflectionException
     * @throws \Swaggest\JsonSchema\InvalidValue
     */
    public function set()
    {
        $jsonSchemaPath =  __DIR__ . '/../../../../inc/validation/schema/glossary/set.json' ;
        $json = $this->createThePayloadForWorker($jsonSchemaPath);

        $keys = [];
        foreach ($json['term']['metadata']['keys'] as $key){
            $keys[] = $key;
        }

        $this->checkWritePermissions($keys, $json['userKeys']);

        $params = [
            'action' => 'set',
            'payload' => $json,
        ];

        $this->enqueueWorker( self::GLOSSARY_WRITE, $params );

        $this->response->json($json);
    }

    /**
     * Update action on MyMemory
     *
     * @throws \ReflectionException
     * @throws \Swaggest\JsonSchema\InvalidValue
     */
    public function update()
    {
        $jsonSchemaPath =  __DIR__ . '/../../../../inc/validation/schema/glossary/update.json' ;
        $json = $this->createThePayloadForWorker($jsonSchemaPath);

        $this->checkWritePermissions([$json['term']['metadata']['key']], $json['userKeys']);

        $params = [
            'action' => 'update',
            'payload' => $json,
        ];

        $this->enqueueWorker( self::GLOSSARY_WRITE, $params );

        $this->response->json($json);
    }

    /**
     * This function validates the payload
     * and returns an object ready for GlossaryWorker
     *
     * @param $jsonSchemaPath
     *
     * @return mixed
     * @throws \ReflectionException
     * @throws \Swaggest\JsonSchema\InvalidValue
     */
    private function createThePayloadForWorker($jsonSchemaPath)
    {
        $jsonSchema = file_get_contents($jsonSchemaPath);
        $this->validateJson($this->request->body(), $jsonSchema);

        $json = json_decode($this->request->body(), true);

        if(isset($json['target_language']) and isset($json['source_language'])){
            $this->validateLanguage($json['target_language']);
            $this->validateLanguage($json['source_language']);
        }

        if(isset($json['term']['target_language']) and isset($json['term']['source_language'])){
            $this->validateLanguage($json['term']['target_language']);
            $this->validateLanguage($json['term']['source_language']);
        }

        $job = \CatUtils::getJobFromIdAndAnyPassword($json['id_job'], $json['password']);

        if($job === null){
            $this->response->code(500);
            $this->response->json([
                    'error' => 'Wrong id_job/password combination'
            ]);
            die();
        }

        $isRevision = \CatUtils::getIsRevisionFromIdJobAndPassword($json['id_job'], $json['password']);

        if ( $isRevision ) {
            $userRole = TmKeyManagement_Filter::ROLE_REVISOR;
        } elseif ( $this->user->email == $job->status_owner ) {
            $userRole = TmKeyManagement_Filter::OWNER;
        } else {
            $userRole = TmKeyManagement_Filter::ROLE_TRANSLATOR;
        }

        $userKeys = new UserKeysModel($this->user, $userRole ) ;

        $json['id_segment'] = (isset($json['id_segment'])) ? $json['id_segment'] : null;
        $json['jobData'] = $job->toArray();
        $json['tmKeys'] = \json_decode($job->tm_keys, true);
        $json['userKeys'] = $userKeys->getKeys( $job->tm_keys )['job_keys'];

        return $json;
    }

    /**
     * @param array $keys
     * @param \TmKeyManagement_ClientTmKeyStruct[] $userKeys
     */
    private function checkWritePermissions(array $keys, array $userKeys)
    {
        $allowedKeys = [];

        foreach ($userKeys as $userKey){
            $allowedKeys[] = $userKey->key;
        }

        // loop $keys
        foreach ($keys as $key){

            // check if $key is allowed
            if(!in_array($key, $allowedKeys)){
                $this->response->code(500);
                $this->response->json([
                    'error' => "Key ".$key." does not belong to this job"
                ]);
                die();
            }

            // check key permissions
            $keyIsUse = array_filter($userKeys, function (\TmKeyManagement_ClientTmKeyStruct $userKey) use ($key){
                return $userKey->key === $key;
            })[0];

            // is a glossary key?
            if($keyIsUse->glos === false){
                $this->response->code(500);
                $this->response->json([
                        'error' => "Key ".$key." is not a glossary key"
                ]);
                die();
            }

            // write permissions?
            if($keyIsUse->edit === false or $keyIsUse->w === 0){
                $this->response->code(500);
                $this->response->json([
                        'error' => "Key ".$key." has not write permissions"
                ]);
                die();
            }
        }
    }

    /**
     * @param $json
     * @param $jsonSchema
     *
     * @throws \Swaggest\JsonSchema\InvalidValue
     */
    private function validateJson($json, $jsonSchema)
    {
        $validatorObject = new JSONValidatorObject();
        $validatorObject->json = $json;

        $validator = new \Validator\JSONValidator($jsonSchema);
        $validator->validate($validatorObject);

        if(!$validator->isValid()){

            $error = $validator->getErrors()[0]->error;

            $this->response->code(500);
            $this->response->json([
                    'error' => $error->getMessage()
            ]);
            die();
        }
    }

    /**
     * @param $language
     */
    private function validateLanguage($language){

        if(!in_array($language, $this->allowedLanguages())){
            $this->response->code(500);
            $this->response->json([
                    'error' => $language . ' is not an allowed language'
            ]);
            die();
        }
    }

    /**
     * @return array
     */
    private function allowedLanguages()
    {
        $allowedLanguages = [];

        $file = \INIT::$UTILS_ROOT . '/Langs/supported_langs.json';
        $string = file_get_contents( $file );
        $langs = json_decode( $string, true );

        foreach ($langs['langs'] as $lang){
            $allowedLanguages[] = $lang['rfc3066code'];
        }

        return $allowedLanguages;
    }

    /**
     * Enqueue a Worker
     *
     * @param $queue
     * @param $params
     */
    private function enqueueWorker( $queue, $params ) {
        try {
            \WorkerClient::enqueue( $queue, '\AsyncTasks\Workers\GlossaryWorker', $params, [ 'persistent' => \WorkerClient::$_HANDLER->persistent ] );
        } catch ( \Exception $e ) {
            # Handle the error, logging, ...
            $output = "**** Glossary enqueue request failed. AMQ Connection Error. ****\n\t";
            $output .= "{$e->getMessage()}";
            $output .= var_export( $params, true );
            \Log::doJsonLog( $output );
            \Utils::sendErrMailReport( $output );
        }
    }
}
