<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 27/10/17
 * Time: 13.25
 *
 */

namespace API\V2;


use API\V2\Json\Engine;
use API\V2\Validators\LoginValidator;
use Database;
use EnginesModel_EngineDAO;
use EnginesModel_EngineStruct;

class EnginesController extends KleinController {

    public function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function listEngines(){

        $engineDAO        = new EnginesModel_EngineDAO( Database::obtain() );
        $engineStruct     = EnginesModel_EngineStruct::getStruct();
        $engineStruct->uid = $this->user->uid;
        $engineStruct->active = true;

        $eng = $engineDAO->setCacheTTL( 60 * 5 )->read( $engineStruct );
        $formatter = new Engine( $eng );
        $this->response->json( $formatter->render() );

    }

}