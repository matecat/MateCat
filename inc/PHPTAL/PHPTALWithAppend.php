<?php

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 02/05/16
 * Time: 11:09
 */
class PHPTALWithAppend extends PHPTAL {

    protected $internal_store = array();

    /**
     *
     * This method populates an array of arrays that can be used
     * to push values on the template so that plugins can append
     * their own javascripts or assets.
     *
     * @param $name
     * @param $value
     */
    public function append($name, $value) {
        if (! array_key_exists($name, $this->internal_store) ) {
            $this->internal_store[ $name ] = array() ;
        }

        array_push( $this->internal_store[ $name ], $value );

        $this->$name = $this->internal_store[ $name ] ;
    }
}