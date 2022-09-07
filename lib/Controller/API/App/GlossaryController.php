<?php

namespace API\App;

use API\V2\KleinController;
use API\V2\Validators\LoginValidator;
use TmKeyManagement\UserKeysModel;
use TmKeyManagement_Filter;
use Validator\JSONValidatorObject;

class GlossaryController extends KleinController {

    const GLOSSARY_WRITE = 'GLOSSARY_WRITE';
    const GLOSSARY_READ  = 'GLOSSARY_READ';

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * Delete action on MyMemory
     *
     * @throws \ReflectionException
     * @throws \Swaggest\JsonSchema\InvalidValue
     */
    public function delete()
    {
        $jsonSchemaPath =  __DIR__ . '/../../../../inc/validation/schema/glossary/delete.json' ;
        $json = $this->createThePayloadForWorker($jsonSchemaPath);

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
    private function createThePayloadForWorker( $jsonSchemaPath)
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


        $json['jobData'] = $job->toArray();
        $json['tm_keys'] = $job->tm_keys;
        $json['jobKeys'] = $userKeys->getKeys( $job->tm_keys )['job_keys'];

        return $json;
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
