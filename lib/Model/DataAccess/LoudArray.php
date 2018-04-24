<?php

namespace DataAccess;

use ArrayAccess;
use DataAccess_AbstractDaoObjectStruct;

/**
 *
 * Class LoudArray
 * @package DataAccess
 */
class LoudArray extends DataAccess_AbstractDaoObjectStruct implements ArrayAccess {

    use ArrayAccessTrait;

    protected function tryValidator() {}

    /**
     * __set() is run when writing data to inaccessible properties
     *
     * @param $name
     * @param $value
     */
    public function __set( $name, $value ) {
        $this->$name = $value;
    }

}