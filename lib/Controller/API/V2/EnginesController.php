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
use Exception;
use Model\Engines\EngineDAO;
use Model\Engines\Structs\EngineStruct;
use View\API\V2\Json\Engine;

class EnginesController extends KleinController
{

    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * @throws Exception
     */
    public function listEngines(): void
    {
        $engineDAO = new EngineDAO($this->db());
        $engineStruct = EngineStruct::getStruct();
        $engineStruct->uid = $this->user->uid;
        $engineStruct->active = true;

        $eng = $engineDAO->setCacheTTL(60 * 5)->read($engineStruct);
        $formatter = new Engine($eng);
        $this->response->json($formatter->render());
    }

}