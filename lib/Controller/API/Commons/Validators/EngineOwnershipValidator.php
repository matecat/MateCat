<?php

namespace Controller\API\Commons\Validators;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthorizationError;
use Utils\Engines\AbstractEngine;
use Utils\Engines\EnginesFactory;

/**
 * @template T of AbstractEngine
 */
class EngineOwnershipValidator extends Base
{

    private int $engineId;

    /** @var class-string<T> */
    private string $engineClass;

    /** @var T */
    private AbstractEngine $engine;

    /**
     * @param KleinController $controller
     * @param int $engineId
     * @param class-string<T> $engineClass
     */
    public function __construct(KleinController $controller, int $engineId, string $engineClass)
    {
        parent::__construct($controller);
        $this->engineId = $engineId;
        $this->engineClass = $engineClass;
    }

    /**
     * @throws AuthorizationError
     * @throws \Exception
     */
    protected function _validate(): void
    {
        $user = $this->controller->getUser();
        if ($user->uid === null) {
            throw new AuthorizationError("Not Authorized. You must be logged in.", 401);
        }

        /** @var T $engine */
        $engine = EnginesFactory::getInstanceByIdAndUser($this->engineId, $user->uid, $this->controller->getDatabase(), $this->engineClass);
        $this->engine = $engine;
    }

    /**
     * @return T
     */
    public function getEngine(): AbstractEngine
    {
        return $this->engine;
    }

}