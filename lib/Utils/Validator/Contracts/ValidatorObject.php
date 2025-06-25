<?php

namespace Validator\Contracts;

use ArrayAccess;
use DataAccess\ArrayAccessTrait;
use stdClass;

class ValidatorObject implements ArrayAccess {

    use ArrayAccessTrait;

    /**
     * @param stdClass $object
     *
     * @return ValidatorObject
     */
    public static function fromObject( stdClass $object ): ValidatorObject {
        $that = new static();
        foreach ( get_object_vars( $object ) as $key => $value ) {
            $that->$key = $value;
        }

        return $that;
    }

    /**
     * @param array $array
     *
     * @return ValidatorObject
     */
    public static function fromArray( array $array ): ValidatorObject {
        $that = new static();
        foreach ( $array as $key => $value ) {
            $that->$key = $value;
        }

        return $that;
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
