<?php
/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 18/02/14
 * Time: 18.15
 * 
 */


class ServerCheck_uploadParams extends ServerCheck_params {

    protected $post_max_size       = -1;
    protected $upload_max_filesize = -1;

    /**
     * @return int
     */
    public function getPostMaxSize() {
        return $this->post_max_size;
    }

    /**
     * @return int
     */
    public function getUploadMaxFilesize() {
        return $this->upload_max_filesize;
    }

    public function __set( $name, $value ){
        if( !property_exists( $this, $name ) ){
            throw new DomainException( 'Unknown property ' . $name );
        }
    }

}