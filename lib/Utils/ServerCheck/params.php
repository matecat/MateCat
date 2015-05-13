<?php
/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 18/02/14
 * Time: 18.27
 * 
 */

class ServerCheck_params extends stdClass {
    public function __construct( ServerCheck_params $params = null ){
        if( $params != null ){
            foreach( $params as $property => $value ){
                $this->$property = $value;
            }
        }
    }
}