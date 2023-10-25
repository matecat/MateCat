<?php

namespace API\App;

use Engines_MMT;
use EnginesModel_EngineStruct;

class ModernMTProxyController extends AbstractStatefulKleinController
{
    public function get()
    {
        if(!$this->userIsLogged()){
            $this->response->status()->setCode( 401 );
            $this->response->json([]);
            exit();
        }

        try {
            $engineId = $this->request->engineId;
            $MMTClient = $this->getMyMemoryClient($engineId);
            $memories = $MMTClient->getAllMemories();
            $results = [];

            foreach ($memories as $memory){

                // if($memory['has_glossary'] == true){
                // }

                $results[] = [
                    'id' => $memory['id'],
                    'name' => $memory['name'],
                    'has_glossary' => true
                ];
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
}