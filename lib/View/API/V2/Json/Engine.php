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

    /**
     * @param EngineStruct[] $data
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * @param EngineStruct[] $data
     *
     * @return array<int, array<string, mixed>>
     */
    public function render(array $data = []): array
    {
        $out = [];

        if (empty($data)) {
            $data = $this->data;
        }

        foreach ($data as $engine) {
            $out[] = $engine->arrayRepresentation();
        }

        return $out;
    }

}