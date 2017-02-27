<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 27/02/2017
 * Time: 12:10
 */

namespace Features\Dqf\Service;


class AbstractResponseStruct {

    public function __construct( $array_params ) {
        if ( $array_params != null ) {
            foreach ( $array_params as $property => $value ) {
                $this->$property = $value;
            }
        }
    }
}