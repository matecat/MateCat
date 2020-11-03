<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 23/12/15
 * Time: 12.32
 *
 */

namespace TaskRunner\Commons;

/**
 * Class Params
 * Generic parameters container
 *
 * @package TaskRunner\Commons
 */
class Params extends AbstractElement {

    /**
     * Magic to string method
     *
     * @return string
     */
    public function __toString() {
        return json_encode($this);
    }

    /**
     * __set() is run when writing data to inaccessible ( or not existent ) properties
     *
     * @param $name
     * @param $value
     */
    public function __set( $name, $value ) {
        $this->$name = $value;
    }

}