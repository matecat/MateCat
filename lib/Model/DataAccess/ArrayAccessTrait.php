<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 16/04/18
 * Time: 13.36
 *
 */

namespace DataAccess;

trait ArrayAccessTrait {

    /**
     * This method is executed when using isset() or empty() on objects implementing ArrayAccess.
     *
     * @param mixed $offset
     *
     * @return bool
     */
    public function offsetExists( $offset ) {
        return property_exists( $this, $offset );
    }

    /**
     * @param mixed $offset
     *
     * @returns mixed
     * @return
     */
    public function offsetGet( $offset ) {
        return $this->__get( $offset );
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet( $offset, $value ) {
        $this->__set( $offset, $value );
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset( $offset ) {
        $this->__set( $offset, null );
    }

}