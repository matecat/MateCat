<?php

namespace Model\LQA;

use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;

class CategoryStruct extends AbstractDaoSilentStruct implements IDaoStruct {

    public ?int   $id = null;
    public string $severities;

    public int     $id_model;
    public ?int    $id_parent = null;
    public string  $label;
    public ?string $options   = null;

    /**
     * @return mixed
     */
    public function getJsonSeverities() {
        return json_decode( $this->severities, true );
    }

    public function toArrayWithJsonDecoded(): array {
        $result = $this->toArray();

        $severities      = json_decode( $this->severities, true );
        $severitiesArray = [];

        foreach ( $severities as $index => $severity ) {
            $severitiesArray[ $index ] = array_merge( [ 'id' => null ], $severity );
        }

        $result[ 'severities' ] = $severitiesArray;
        $result[ 'options' ]    = json_decode( $this->options, true );

        return $result;
    }
}
