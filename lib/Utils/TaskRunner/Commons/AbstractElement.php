<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 23/12/15
 * Time: 21.00
 *
 */

namespace TaskRunner\Commons;
use ArrayAccess;
use DomainException;
use stdClass;

/**
 * Class AbstractElement
 *
 * Generic class for an element queue
 *
 * @package TaskRunner\Commons
 */
abstract class AbstractElement extends stdClass implements ArrayAccess {

    /**
     * AbstractElement constructor.
     *
     * @param array $array_params
     */
    public function __construct( Array $array_params = array() ) {
        if ( $array_params != null ) {
            foreach ( $array_params as $property => $value ) {
                if( is_array( $value ) ){
                    $value = new Params( $value );
                }
                $this->$property = $value;
            }
        }
    }

    /**
     * __set() is run when writing data to inaccessible properties
     *
     * @param $name
     * @param $value
     */
    public function __set( $name, $value ) {
        throw new DomainException( 'Unknown property ' . $name );
    }

    /**
     * Object to Array conversion method
     * @return array
     */
    public function toArray(){
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
        if( $this->offsetExists( $offset ) ) return $this->$offset;
        return null;
    }

    /**
     * ArrayAccess interface implementation
     *
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet( $offset, $value ) {
        if( $this->offsetExists( $offset ) ) $this->$offset = $value;
    }

    /**
     * ArrayAccess interface implementation
     *
     * @param mixed $offset
     */
    public function offsetUnset( $offset ) {
        if( $this->offsetExists( $offset ) ) $this->$offset = null;
    }

}