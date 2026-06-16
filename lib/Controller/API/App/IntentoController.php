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
use Exception;
use Model\Engines\EngineDAO;
use Model\Engines\Structs\EngineStruct;
use TypeError;
use UnexpectedValueException;
use Utils\Engines\EnginesFactory;
use Utils\Engines\Intento;

class IntentoController extends KleinController
{

    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    public function routingList(): void
    {
        $engineId = $this->request->param("engineId");
        $engine = new EngineStruct();
        $engine->id = $engineId;

        $engineDAO = new EngineDAO($this->db());
        $engineStruct = ($engineDAO->setCacheTTL(60 * 60 * 5)->read($engine)[0] ?? null) ?: throw new UnexpectedValueException('Engine ID is not valid');
        $newTestCreatedMT = EnginesFactory::createTempInstance($engineStruct);

        $newTestCreatedMT instanceof Intento || throw new UnexpectedValueException('Engine is not of Intento type');

        $this->response->json($newTestCreatedMT->getRoutingList());
    }

}