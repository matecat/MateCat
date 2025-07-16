<?php

namespace API\V3;

use API\Commons\Validators\LoginValidator;
use API\V2\BaseChunkController;
use Exception;
use Utils\Engines\Lara;
use Validator\EngineValidator;


class LaraController extends BaseChunkController {
    protected function afterConstruct() {
        parent::afterConstruct();
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * Get all the customer's Lara glossaries
     */
    public function glossaries() {
        if ( !$this->isLoggedIn() ) {
            $this->response->status()->setCode( 401 );
            $this->response->json( [] );
            exit();
        }

        try {
            $engineId  = filter_var( $this->request->engineId, FILTER_SANITIZE_NUMBER_INT );

            /** @var Lara $LaraClient */
            $LaraClient = $this->getLaraClient( $engineId );
            $glossaries  = $LaraClient->getGlossaries();

            $this->response->status()->setCode( 200 );
            $this->response->json( $glossaries );
            exit();

        } catch ( Exception $exception ) {
            $code = ( $exception->getCode() > 0 ) ? $exception->getCode() : 500;
            $this->response->status()->setCode( $code );
            $this->response->json( [
                    'error' => $exception->getMessage()
            ] );
            exit();
        }
    }

    /**
     * @param $engineId
     *
     * @return \Engines_AbstractEngine
     * @throws Exception
     */
    private function getLaraClient( $engineId ) {
        return EngineValidator::engineBelongsToUser( $engineId, $this->user->uid, Lara::class );
    }
}