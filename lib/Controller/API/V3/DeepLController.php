<?php

namespace API\V3;

use API\V2\KleinController;
use API\V2\Validators\LoginValidator;
use Engines\DeepL;
use Exception;
use Validator\EngineValidator;

class DeepLController extends KleinController
{
    protected function afterConstruct() {
        parent::afterConstruct();
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * Get all glossaries
     */
    public function glossaries()
    {
        try {
            $engineId = filter_var( $this->request->engineId, FILTER_SANITIZE_NUMBER_INT );
            $deepLClient = $this->getDeepLClient($engineId);

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

    /**
     * @param $engineId
     * @return \Engines_AbstractEngine
     * @throws Exception
     */
    private function getDeepLClient($engineId)
    {
        return EngineValidator::engineBelongsToUser($engineId, $this->user->uid, DeepL::class);
    }
}