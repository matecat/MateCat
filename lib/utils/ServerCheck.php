<?php
/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 17/02/14
 * Time: 15.20
 * 
 */

class ServerCheck {

    protected static $_INSTANCE;

    protected static $serverParams = array();
    protected static $uploadParams = array(
            'post_max_size'       => -1,
            'upload_max_filesize' => -1
    );

    protected function __construct(){

        self::$serverParams = array(
                'upload' => self::$uploadParams
        );

        //init class loading server params
        $this->checkUploadParams();

    }

    public static function getInstance(){
        if( self::$_INSTANCE == null ){
            self::$_INSTANCE = new self();
        }
        return self::$_INSTANCE;
    }

    protected function checkUploadParams(){

        $regexp = '/([0-9]+)(G|M)?/';
        preg_match( $regexp, ini_get('post_max_size'), $matches );
        if( isset( $matches[2] ) ){
            switch( $matches[2] ){
                case "M":
                    $allowed_post = (int)$matches[1] * 1024 * 1024;
                    break;
                case "G":
                    $allowed_post = (int)$matches[1] * 1024 * 1024 * 1024;
                    break;
            }
        } else { $allowed_post = (int)$matches[1]; }

        self::$serverParams['upload']['post_max_size'] = $allowed_post;
        self::$uploadParams['post_max_size'] = $allowed_post;

        preg_match( $regexp, ini_get('upload_max_filesize'), $matches );
        if( isset( $matches[2] ) ){
            switch( $matches[2] ){
                case "M":
                    $allowed_upload = (int)$matches[1] * 1024 * 1024;
                    break;
                case "G":
                    $allowed_upload = (int)$matches[1] * 1024 * 1024 * 1024;
                    break;
            }
        } else { $allowed_upload = (int)$matches[1]; }

        self::$serverParams['upload']['upload_max_filesize'] = $allowed_upload;
        self::$uploadParams['upload_max_filesize'] = $allowed_upload;

        return $this;

    }

    public function getUploadParams(){
        return self::$uploadParams;
    }

    public function getServerParams(){
        return self::$serverParams;
    }

} 