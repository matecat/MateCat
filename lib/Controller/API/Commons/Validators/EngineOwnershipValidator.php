<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 19/08/25
 * Time: 18:06
 *
 */

namespace Controller\API\Commons\Validators;

use Controller\Abstracts\KleinController;
use Utils\Engines\AbstractEngine;
use Utils\Engines\EnginesFactory;

/**
 * @template T of AbstractEngine
 */
class EngineOwnershipValidator extends Base {


    private int     $engineId;
    private ?string $engineClass;

    /**
     * @var T
     */
    private AbstractEngine $engine;

    /**
     * @param KleinController $controller
     * @param int             $engineId
     * @param class-string<T> $engineClass
     */
    public function __construct( KleinController $controller, int $engineId, string $engineClass ) {

        parent::__construct( $controller );
        $this->engineId    = $engineId;
        $this->engineClass = $engineClass;

    }

    protected function _validate(): void {
        $this->engine = EnginesFactory::getInstanceByIdAndUser( $this->engineId, $this->controller->getUser()->uid, $this->engineClass );
    }

    /**
     * @return T
     */
    public function getEngine(): AbstractEngine {
        return $this->engine;
    }

}