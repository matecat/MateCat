<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 07/06/17
 * Time: 16.39
 *
 */

namespace DataAccess;

use ArrayAccess;
use DataAccess_AbstractDaoObjectStruct;

class ShapelessConcreteStruct extends DataAccess_AbstractDaoObjectStruct implements ArrayAccess {

    use ArrayAccessTrait;

    protected function tryValidator() {}

    public function __set( $name, $value ) {
        $this->$name = $value;
    }

    /**
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

    public function getArrayCopy() {
        return (array)$this;
    }

}