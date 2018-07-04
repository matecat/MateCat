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
                throw new Exception( "Error while retrieving input_filename content: " . $this->input_filename  );

            }

        }

        return $this->document_content;
    }

    /**
     * @param array|ZipContentObject $_array_params
     */
    public function __construct( $_array_params = array() ) {

        //This is a multidimensional array
        if( array_key_exists( 0, $_array_params ) ){
            foreach( $_array_params as $pos => $array_params ){
                $this->build( $array_params );
            }
        } else {
            $this->build( $_array_params );
        }


    }

    public function build( $_array_params ){

        //This is a multidimensional array
        if( array_key_exists( 0, $_array_params ) ){
            foreach( $_array_params as $pos => $array_params ){
                $this->build( $array_params );
            }
        }
        else {
            //this accept instance of SELF also
            if ( !empty( $_array_params ) ) {
                foreach ( $_array_params as $property => $value ) {
                    $this->$property = $value;
                }
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