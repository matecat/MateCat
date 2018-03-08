<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 07/06/17
 * Time: 16.39
 *
 */

namespace DataAccess;


use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;

class ShapelessConcreteStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct, \ArrayAccess {

    public function __set( $name, $value ) {
        $this->$name = $value;
    }

    public function getArrayCopy() {
        return (array)$this;
    }

    /**
     * ArrayAccess interface implementation
     *
     * @param mixed $offset
     *
     * @return bool
     */
    public function offsetExists( $offset ) {
        return property_exists( $this, $offset );
    }

    /**
     * ArrayAccess interface implementation
     *
     * @param mixed $offset
     *
     * @return null
     */
    public function offsetGet( $offset ) {
        if ( $this->offsetExists( $offset ) ) {
            return $this->$offset;
        }
        return null;
    }

    /**
     * ArrayAccess interface implementation
     *
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet( $offset, $value ) {
        if ( $this->offsetExists( $offset ) ) {
            $this->$offset = $value;
        }
    }

    /**
     * ArrayAccess interface implementation
     *
     * @param mixed $offset
     */
    public function offsetUnset( $offset ) {
        if ( $this->offsetExists( $offset ) ) {
            $this->$offset = null;
        }
    }

}