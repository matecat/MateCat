<?php

namespace API\V3;

use API\V2\Validators\LoginValidator;
use Engines_MMT;
use Exception;
use Engine;
use API\V2\BaseChunkController;
use Validator\EngineValidator;
use Validator\MMTValidator;

class ModernMTProxyController extends BaseChunkController
{
    protected function afterConstruct() {
        parent::afterConstruct();
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * Get all the customer's memories
     */
    public function get()
    {
        if(!$this->userIsLogged()){
            $this->response->status()->setCode( 401 );
            $this->response->json([]);
            exit();
        }

        try {
            $engineId = $this->request->engineId;
            $params = $this->request->params();
            $MMTClient = $this->getModernMTClient($engineId);
            $memories = $MMTClient->getAllMemories();
            $results = [];

            foreach ($memories as $memory){
                if($this->filterResult($params, $memory)){
                    $results[] = $this->buildResult($memory);
                }
            }

            $this->response->status()->setCode( 200 );
            $this->response->json($results);
            exit();

        } catch (Exception $exception){
            $this->response->status()->setCode( 500 );
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
    private function getModernMTClient($engineId)
    {
        return EngineValidator::engineBelongsToUser($engineId, $this->user->uid, Engines_MMT::class);
    }

    /**
     * @param $params
     * @param $memory
     * @return bool
     */
    private function filterResult($params, $memory)
    {
        if(isset($params['q'])){
            $q = filter_var($params['q'], [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW  ] );
            if(false === strpos($memory['name'], $q)){
                return false;
            }
        }

        if(isset($params['has_glossary'])){
            $hasGlossary = filter_var($params['has_glossary'], FILTER_VALIDATE_BOOLEAN);

            if($memory['has_glossary'] != $hasGlossary){
                return false;
            }
        }

        return true;
    }

    /**
     * @param $memory
     * @return array
     */
    private function buildResult($memory)
    {
        return [
            'id' => $memory['id'],
            'name' => $memory['name'],
            'has_glossary' => (isset($memory['has_glossary']) ? $memory['has_glossary'] : false),
        ];
    }
}