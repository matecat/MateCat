<?php

namespace API\App;

use AbstractControllers\KleinController;
use API\Commons\Validators\LoginValidator;
use Engine;
use Engines_MyMemory;
use Exception;
use FeatureSet;

class MyMemoryController extends KleinController {

    /**
     * @return void
     */
    public function status() {
        try {
            $uuid = $this->request->param('uuid');
            $mmEngine = $this->getMMEngine($this->featureSet);
            $status = $mmEngine->entryStatus($uuid);
            $this->response->json( $status );
        } catch (Exception $exception){
            $this->response->status()->setCode( 500 );
            $this->response->json( [
                'error' => $exception->getMessage()
            ] );
        }
    }

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * @param FeatureSet $featureSet
     *
     * @return Engines_MyMemory
     * @throws Exception
     */
    private function getMMEngine(FeatureSet $featureSet ) {
        $_TMS = Engine::getInstance( 1 );
        $_TMS->setFeatureSet( $featureSet );

        /** @var Engines_MyMemory $_TMS */
        return $_TMS;
    }
}
