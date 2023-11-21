<?php

namespace API\V3;

use API\V2\KleinController;
use API\V2\Validators\LoginValidator;
use Engines_DeepL;
use Exception;
use Validator\EngineValidator;

class DeepLGlossaryController extends KleinController
{
    protected function afterConstruct() {
        parent::afterConstruct();
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * Get all glossaries
     */
    public function all()
    {
        try {
            $engineId = filter_var( $this->request->engineId, FILTER_SANITIZE_NUMBER_INT );
            $deepLClient = $this->getDeepLClient($engineId);

            $deepLApiKey = $deepLClient->getEngineRecord()->extra_parameters['DeepL-Auth-Key'];
            $deepLClient->setApiKey($deepLApiKey);

            $this->response->status()->setCode( 200 );
            $this->response->json($deepLClient->glossaries());
            exit();

        } catch (Exception $exception){
            $code = ($exception->getCode() > 0) ? $exception->getCode() : 500;
            $this->response->status()->setCode( $code );
            $this->response->json([
                'error' => $exception->getMessage()
            ]);
            exit();
        }
    }

    public function create()
    {

    }

    /**
     * Delete a single glossary item
     */
    public function delete()
    {
        try {
            $id = filter_var( $this->request->id, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH|FILTER_FLAG_ENCODE_LOW );
            $engineId = filter_var( $this->request->engineId, FILTER_SANITIZE_NUMBER_INT );
            $deepLClient = $this->getDeepLClient($engineId);

            $deepLApiKey = $deepLClient->getEngineRecord()->extra_parameters['DeepL-Auth-Key'];
            $deepLClient->setApiKey($deepLApiKey);

            $this->response->status()->setCode( 200 );
            $this->response->json($deepLClient->delete($id));
            exit();

        } catch (Exception $exception){
            $code = ($exception->getCode() > 0) ? $exception->getCode() : 500;
            $this->response->status()->setCode( $code );
            $this->response->json([
                'error' => $exception->getMessage()
            ]);
            exit();
        }
    }

    /**
     * Get a single glossary item
     */
    public function get()
    {
        try {
            $id = filter_var( $this->request->id, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH|FILTER_FLAG_ENCODE_LOW );
            $engineId = filter_var( $this->request->engineId, FILTER_SANITIZE_NUMBER_INT );
            $deepLClient = $this->getDeepLClient($engineId);

            $deepLApiKey = $deepLClient->getEngineRecord()->extra_parameters['DeepL-Auth-Key'];
            $deepLClient->setApiKey($deepLApiKey);

            $this->response->status()->setCode( 200 );
            $this->response->json($deepLClient->getGlossary($id));
            exit();

        } catch (Exception $exception){
            $code = ($exception->getCode() > 0) ? $exception->getCode() : 500;
            $this->response->status()->setCode( $code );
            $this->response->json([
                'error' => $exception->getMessage()
            ]);
            exit();
        }
    }

    /**
     * Get a single glossary item entries
     */
    public function getEntries()
    {
        try {
            $id = filter_var( $this->request->id, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH|FILTER_FLAG_ENCODE_LOW );
            $engineId = filter_var( $this->request->engineId, FILTER_SANITIZE_NUMBER_INT );
            $deepLClient = $this->getDeepLClient($engineId);

            $deepLApiKey = $deepLClient->getEngineRecord()->extra_parameters['DeepL-Auth-Key'];
            $deepLClient->setApiKey($deepLApiKey);

            $this->response->status()->setCode( 200 );
            $this->response->json($deepLClient->getGlossaryEntries($id));
            exit();

        } catch (Exception $exception){
            $code = ($exception->getCode() > 0) ? $exception->getCode() : 500;
            $this->response->status()->setCode( $code );
            $this->response->json([
                'error' => $exception->getMessage()
            ]);
            exit();
        }
    }

    /**
     * @param $engineId
     * @return \Engines_AbstractEngine
     * @throws Exception
     */
    private function getDeepLClient($engineId)
    {
        $engine = EngineValidator::engineBelongsToUser($engineId, $this->user->uid, Engines_DeepL::class);
        $extraParams = $engine->getEngineRecord()->extra_parameters;

        if(!isset($extraParams['DeepL-Auth-Key'])){
            throw new Exception('`DeepL-Auth-Key` is not set');
        }

        return $engine;
    }
}