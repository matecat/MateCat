<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 02/02/2017
 * Time: 17:36
 */

namespace View\API\V2\Json;


use Model\Engines\EngineStruct;

class Engine {

    private $data;

    public function __construct( $data = null ) {
        $this->data = $data;
    }

    /**
     * @param EngineStruct $engine
     *
     * @return array
     */
    public function renderItem( EngineStruct $engine ) {
        $engine_type = explode( "\\", $engine->class_load );
        return [
                'id'          => $engine->id,
                'name'        => $engine->name,
                'type'        => $engine->type,
                'description' => $engine->description,
                'engine_type' => array_pop( $engine_type )
        ];
    }

    public function render( $data = null ) {
        $out = [];

        if ( empty( $data ) ) {
            $data = $this->data;
        }

        /**
         * @var $data EngineStruct[]
         */
        foreach ( $data as $k => $engine ) {
            $out[] = $this->renderItem( $engine );
        }

        return $out;
    }

}