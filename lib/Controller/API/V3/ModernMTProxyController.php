<?php

namespace API\V3;

use API\V2\Validators\LoginValidator;
use Engines_MMT;
use EnginesModel_EngineStruct;
use API\V2\BaseChunkController;

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
            $MMTClient = $this->getMyMemoryClient($engineId);
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

        } catch (\Exception $exception){
            $this->response->status()->setCode( 500 );
            $this->response->json([
                'error' => $exception->getMessage()
            ]);
            exit();
        }
    }

    /**
     * @param $id
     * @return Engines_MMT
     * @throws \Exception
     */
    private function getMyMemoryClient($id)
    {
        $engineDAO        = new \EnginesModel_EngineDAO( \Database::obtain() );
        $engineStruct     = \EnginesModel_EngineStruct::getStruct();
        $engineStruct->id = $id;

        $eng = $engineDAO->setCacheTTL( 60 * 5 )->read( $engineStruct );

        if(empty($eng)){
            throw new \Exception("Engine not found");
        }

        /**
         * @var $engineRecord EnginesModel_EngineStruct
         */
        $engineRecord = @$eng[ 0 ];

        if($engineRecord->uid !== $this->user->uid){
            throw new \Exception("Engine not belongs to the logged user");
        }

        return new Engines_MMT( $engineRecord );
    }

    /**
     * @param $params
     * @param $memory
     * @return bool
     */
    private function filterResult($params, $memory)
    {
        if(isset($params['q'])){
            if(false === strpos($memory['name'], $params['q'])){
                return false;
            }
        }

        // has_glossary
        if(isset($params['has_glossary'])){
            if($memory['has_glossary'] != $params['has_glossary']){
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
            'has_glossary' => true
        ];
    }
}