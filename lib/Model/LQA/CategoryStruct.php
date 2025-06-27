<?php

namespace Model\LQA;

use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;

class CategoryStruct extends AbstractDaoSilentStruct implements IDaoStruct {

    public $id;
    public $severities;

    public $id_model;
    public $id_parent;
    public $label;
    public $options;

    /**
     * @return mixed
     */
    public function getJsonSeverities() {
        return json_decode( $this->severities, true );
    }

    public function toArrayWithJsonDecoded() {
        $result = $this->toArray();

        $severities      = json_decode( $this->severities, true );
        $severitiesArray = [];

        foreach ( $severities as $index => $severity ) {
            $severitiesArray[ $index ] = array_merge( [ 'id' => null ], $severities[ $index ] );
        }

        $result[ 'severities' ] = $severitiesArray;
        $result[ 'options' ]    = json_decode( $this->options, true );

        return $result;
    }
}
