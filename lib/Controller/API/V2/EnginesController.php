<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 27/10/17
 * Time: 13.25
 *
 */

namespace Controller\API\V2;


use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Database;
use Model\Engines\EngineDAO;
use Model\Engines\EngineStruct;
use View\API\V2\Json\Engine;

class EnginesController extends KleinController {

    public function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function listEngines() {

        $engineDAO            = new EngineDAO( Database::obtain() );
        $engineStruct         = EngineStruct::getStruct();
        $engineStruct->uid    = $this->user->uid;
        $engineStruct->active = true;

        $eng       = $engineDAO->setCacheTTL( 60 * 5 )->read( $engineStruct );
        $formatter = new Engine( $eng );
        $this->response->json( $formatter->render() );

    }

}