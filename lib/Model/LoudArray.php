<?php

class LoudArray extends stdClass implements DataAccess_IDaoStruct, ArrayAccess {

    /**
     * AbstractElement constructor.
     *
     * @param array $array_params
     */
    public function __construct( Array $array_params = array() ) {
        if ( $array_params != null ) {
            foreach ( $array_params as $property => $value ) {
                $this->$property = $value;
            }
        }
    }

    /**
     * __get() is run when reading data from inaccessible properties
     *
     * @param $offset
     */
    public function __get( $offset ) {
        if ( !property_exists( $this, $offset ) ) {
            throw new DomainException( 'Trying to access undefined key ' . $offset );
        }
    }

    /**
     * __set() is run when writing data to inaccessible properties
     *
     * @param $name
     * @param $value
     */
    public function __set( $name, $value ) {
        $this->$name = $value;
    }

    public function getArrayCopy() {
        return (array)$this;
    }

    /**
     * Object to Array conversion method
     * @return array
     */
    public function toArray() {
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
        throw new DomainException( 'Trying to access undefined key ' . $offset );
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