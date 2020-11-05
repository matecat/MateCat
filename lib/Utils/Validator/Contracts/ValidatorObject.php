<?php

namespace Validator\Contracts;

use DataAccess\ArrayAccessTrait;

abstract class ValidatorObject implements \ArrayAccess {

    use ArrayAccessTrait;

    /**
     * @param \stdClass $object
     */
    public function hydrateFromObject(\stdClass $object){
        foreach (get_object_vars($object) as $key => $value){
            $this->$key = $value;
        }
    }

    /**
     * Magic setter
     *
     * @param $name
     * @param $value
     */
    public function __set( $name, $value ) {
        $this->$name = $value;
    }

    /**
     * Magic getter
     *
     * @param $name
     *
     * @return mixed
     */
    public function __get( $name ) {
        if ( !property_exists( $this, $name ) ) {
            return null;
        }

        return $this->$name;
    }
}
