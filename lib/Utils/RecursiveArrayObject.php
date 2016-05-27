<?php
/**
 * User: domenico
 * Date: 23/10/13
 * Time: 16.14
 *
 */

class RecursiveArrayObject extends ArrayObject {

    /**
     * overwrites the ArrayObject constructor for
     * iteration through the "array". When the item
     * is an array, it creates another static() instead
     * of an array
     *
     * @param array $array
     * @param int  $flag
     * @param string  $iteratorClass
     */
    public function __construct( Array $array = array(), $flag = 0, $iteratorClass = 'ArrayIterator' ) {
        foreach ( $array as $key => $value ) {
            if ( is_array( $value ) ) {
                $value = new static( $value, $flag, $iteratorClass );
            }
            $this->offsetSet( $key, $value );
        }
    }

    /**
     * returns Array when printed (like "echo array();")
     * instead of an error
     *
     * @return string
     */
    public function __toString() {
        return 'Array';
    }

}