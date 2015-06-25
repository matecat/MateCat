<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 25/06/15
 * Time: 12.17
 */
class ZipContentObject extends stdClass {

    public $output_filename;
    public $input_filename;
    public $document_content;

    public function getContent() {
        if ( !empty( $this->document_content ) ) {
            return $this->document_content;

        } elseif ( !empty( $this->input_filename ) ) {
            if ( is_file( $this->input_filename ) ) {
                $this->document_content = file_get_contents( $this->input_filename );

            } else {
                throw new Exception( "Error while retrieving input_filename content" );

            }

        }

        return $this->document_content;
    }

    public function __construct( Array $array_params = array() ) {
        if ( $array_params != null ) {
            foreach ( $array_params as $property => $value ) {
                $this->$property = $value;
            }
        }
    }

    public function __set( $name, $value ) {
        if ( !property_exists( $this, $name ) ) {
            throw new DomainException( 'Unknown property ' . $name );
        }
    }

    public function toArray(){
        return (array)$this;
    }

}