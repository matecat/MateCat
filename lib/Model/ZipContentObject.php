<?php

use FilesStorage\AbstractFilesStorage;
use FilesStorage\S3FilesStorage;
use Predis\Connection\ConnectionException;

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

    /**
     * @return string
     * @throws Exception
     */
    public function getContent() {
        if ( !empty( $this->document_content ) ) {
            return $this->document_content;
        }

        if ( !empty( $this->input_filename ) ) {
            if ( AbstractFilesStorage::isOnS3() and false === file_exists( $this->input_filename ) ) {
                $this->setDocumentContentFromS3();
            } else {
                $this->setDocumentContentFromFileSystem();
            }
        }

        return $this->document_content;
    }

    /**
     * @throws ReflectionException
     * @throws ConnectionException
     */
    private function setDocumentContentFromS3() {
        $s3Client = S3FilesStorage::getStaticS3Client();
        $config   = [
                'bucket' => S3FilesStorage::getFilesStorageBucket(),
                'key'    => $this->input_filename,
        ];

        if ( $s3Client->hasItem( $config ) ) {
            $this->document_content = $s3Client->openItem( $config );
        } else {
            throw new Exception( "File: " . $this->input_filename . " is not present in S3 storage bucket. " );
        }
    }

    /**
     * @throws Exception
     */
    private function setDocumentContentFromFileSystem() {
        if ( is_file( $this->input_filename ) ) {
            $this->document_content = file_get_contents( $this->input_filename );
        } else {
            throw new Exception( "Error while retrieving input_filename content: " . $this->input_filename );
        }
    }

    /**
     * @param array|ZipContentObject $_array_params
     */
    public function __construct( $_array_params = [] ) {

        //This is a multidimensional array
        if ( is_array($_array_params) and isset( $_array_params[ 0 ] ) ) {
            foreach ( $_array_params as $pos => $array_params ) {
                $this->build( $array_params );
            }
        } else {
            $this->build( $_array_params );
        }


    }

    public function build( $_array_params ) {

        //This is a multidimensional array
        if ( is_array($_array_params) and isset( $_array_params[ 0 ] ) ) {
            foreach ( $_array_params as $pos => $array_params ) {
                $this->build( $array_params );
            }
        } else {
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

    public function toArray() {
        return (array)$this;
    }

}