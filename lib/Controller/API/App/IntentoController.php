<?php
/**
 * Created by PhpStorm.
 * @author ostico domenico@translated.net / ostico@gmail.com
 * Date: 30/05/19
 * Time: 12.05
 *
 */

namespace Controller\API\App;


use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Model\DataAccess\Database;
use Model\Engines\EngineDAO;
use Model\Engines\Structs\EngineStruct;
use Utils\Engines\EnginesFactory;

class IntentoController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function routingList() {

        $engineId   = $this->request->param( "engineId" );
        $engine     = new EngineStruct();
        $engine->id = $engineId;

        $engineDAO        = new EngineDAO( Database::obtain() );
        $engineStruct     = $engineDAO->setCacheTTL( 60 * 60 * 5 )->read( $engine )[ 0 ];
        $newTestCreatedMT = EnginesFactory::createTempInstance( $engineStruct );

        if ( empty( $engineStruct ) ) {
            $this->response->code( 500 );
            $this->response->json( [
                    'error' => 'Engine ID is not valid'
            ] );
            die();
        }

        $this->response->json( $newTestCreatedMT->getRoutingList() );
    }

}