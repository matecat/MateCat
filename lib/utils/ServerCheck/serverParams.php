<?php
/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 18/02/14
 * Time: 18.15
 * 
 */

class ServerCheck_serverParams extends ServerCheck_params {

    protected $upload;

    /**
     * @return mixed
     */
    public function getUpload() {
        return $this->upload;
    }

    public function __set( $name, $value ){
        if( !property_exists( $this, $name ) ){
            throw new DomainException( 'Unknown property ' . $name );
        }
    }

}
