<?php

namespace API\App;

use API\V2\KleinController;
use API\V2\Validators\LoginValidator;
use TmKeyManagement_Filter;
use Validator\JSONValidatorObject;
use AsyncTasks\Workers\GlossaryWorker;

class GlossaryController extends KleinController {

    const GLOSSARY_WRITE = 'GLOSSARY_WRITE';
    const GLOSSARY_READ  = 'GLOSSARY_READ';

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * Create a new glossary item
     *
     * @throws \ReflectionException
     * @throws \Swaggest\JsonSchema\InvalidValue
     */
    public function create()
    {
        $jsonSchema = file_get_contents( __DIR__ . '/../../../../inc/validation/schema/glossary/crud.json' );
        $this->validateJson($this->request->body(), $jsonSchema);

        $json = json_decode($this->request->body(), true);

        $this->validateLanguage($json['target_language']);
        $this->validateLanguage($json['source_language']);

        $job = \CatUtils::getJobFromIdAndAnyPassword($json['id_job'], $json['password']);

        if($job === null){
            $this->response->code(500);
            $this->response->json([
                    'error' => 'Wrong id_job/password combination'
            ]);
            die();
        }

        $this->featureSet->loadForProject( $job->getProject() );
        $projectFeaturesString = $job->getProject()->getMetadataValue( \Projects_MetadataDao::FEATURES_KEY );

        $config = [];

        $isRevision = \CatUtils::getIsRevisionFromIdJobAndPassword($json['id_job'], $json['password']);
        $userRole = ($isRevision) ? TmKeyManagement_Filter::ROLE_REVISOR : TmKeyManagement_Filter::ROLE_TRANSLATOR;

        $params = [
            'action'  => GlossaryWorker::SET_ACTION,
            'payload' => [
                'id_segment'     => $json['id_segment'],
                'id_client'      => $json['id_client'],
                'tm_keys'        => $job->tm_keys,
                'userRole'       => $userRole,
                'user'           => $this->user->toArray(),
                'featuresString' => $projectFeaturesString,
                'featureSet'     => $this->featureSet,
                'jobData'        => $job->toArray(),
                'tmProps'        => $job->getTMProps(),
                'config'         => $config,
            ],
        ];

       // $this->enqueueWorker( self::GLOSSARY_WRITE, $params );

        $this->response->json([
            'id_segment' => $json['id_segment'],
        ]);
    }

    public function delete()
    {
    }

    public function edit()
    {
    }

    public function show()
    {
    }

    /**
     * Get the domains from MyMemory
     *
     * @throws \ReflectionException
     * @throws \Swaggest\JsonSchema\InvalidValue
     */
    public function domains()
    {
        $jsonSchema = file_get_contents( __DIR__ . '/../../../../inc/validation/schema/glossary/domains.json' );
        $this->validateJson($this->request->body(), $jsonSchema);

        $json = json_decode($this->request->body(), true);

        $this->validateLanguage($json['target_language']);
        $this->validateLanguage($json['source_language']);

        $job = \CatUtils::getJobFromIdAndAnyPassword($json['id_job'], $json['password']);

        if($job === null){
            $this->response->code(500);
            $this->response->json([
                    'error' => 'Wrong id_job/password combination'
            ]);
            die();
        }

        $json['jobData'] = $job->toArray();

        $params = [
            'action' => 'domains',
            'payload' => $json,
        ];

        $this->enqueueWorker( self::GLOSSARY_READ, $params );

        $this->response->json($json);
    }

    public function segment()
    {
    }

    public function segmentSearch()
    {
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
