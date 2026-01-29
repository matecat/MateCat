<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 02/02/2017
 * Time: 17:36
 */

namespace View\API\V2\Json;


use Model\Engines\Structs\EngineStruct;

class Engine
{

    /**
     * @var EngineStruct[]
     */
    private array $data;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * @param EngineStruct[] $data
     *
     * @return array
     */
    public function render(array $data = []): array
    {
        $out = [];

        if (empty($data)) {
            $data = $this->data;
        }

        /**
         * @var $data EngineStruct[]
         */
        foreach ($data as $engine) {
            $out[] = $engine->arrayRepresentation();
        }

        return $out;
    }

}