<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 02/02/2017
 * Time: 17:36
 */

namespace API\V2\Json;


use EnginesModel_EngineStruct;

class Engine {

    private $data;

    public function __construct( $data = null ) {
        $this->data = $data;
    }

    /**
     * @param EnginesModel_EngineStruct $engine
     *
     * @return array
     */
    public function renderItem( EnginesModel_EngineStruct $engine ) {
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
         * @var $data EnginesModel_EngineStruct[]
         */
        foreach ( $data as $k => $engine ) {
            $out[] = $this->renderItem( $engine );
        }

        return $out;
    }

}