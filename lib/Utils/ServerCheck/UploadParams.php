<?php

namespace Utils\ServerCheck;

use DomainException;

/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 18/02/14
 * Time: 18.15
 *
 */
class UploadParams {

    protected int $post_max_size       = -1;
    protected int $upload_max_filesize = -1;

    /**
     * @return int
     */
    public function getPostMaxSize(): int {
        return $this->post_max_size;
    }

    /**
     * @return int
     */
    public function getUploadMaxFilesize(): int {
        return $this->upload_max_filesize;
    }

    public function __set( $name, $value ) {
        if ( !property_exists( $this, $name ) ) {
            throw new DomainException( 'Unknown property ' . $name );
        }
    }

    /**
     * @param int $post_max_size
     *
     * @return $this
     */
    public function setPostMaxSize( int $post_max_size ): UploadParams {
        $this->post_max_size = $post_max_size;

        return $this;
    }

    /**
     * @param int $upload_max_filesize
     *
     * @return $this
     */
    public function setUploadMaxFilesize( int $upload_max_filesize ): UploadParams {
        $this->upload_max_filesize = $upload_max_filesize;

        return $this;
    }

    public function __clone() {
        $cloned = new UploadParams();
        $cloned->setPostMaxSize( $this->getPostMaxSize() );
        $cloned->setUploadMaxFilesize( $this->getUploadMaxFilesize() );
        return $cloned;
    }

}