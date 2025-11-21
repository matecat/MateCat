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
     * @param EngineStruct $engine
     *
     * @return array
     */
    public function renderItem(EngineStruct $engine): array
    {
        $engine_type = explode("\\", $engine->class_load);

        return [
                'id'          => $engine->id,
                'name'        => $engine->name,
                'type'        => $engine->type,
                'description' => $engine->description,
                'engine_type' => array_pop($engine_type)
        ];
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
            $out[] = $this->renderItem($engine);
        }

        return $out;
    }

}