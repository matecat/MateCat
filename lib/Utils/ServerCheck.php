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

    protected static $serverParams;

    /**
     * @var ServerCheck_params
     */
    protected static $uploadParams;

    /**
     * @var ServerCheck_params
     */
    protected static $MysqlParams;

    protected function __construct(){

        self::$uploadParams = new ServerCheck_params();
        self::$serverParams = new ServerCheck_params();

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

        self::$uploadParams->post_max_size = $allowed_post;

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

        self::$uploadParams->upload_max_filesize = $allowed_upload;

        self::$serverParams->upload = new ServerCheck_uploadParams( self::$uploadParams );

        return $this;

    }

    /**
     * @return ServerCheck_uploadParams
     */
    public function getUploadParams(){
        return new ServerCheck_uploadParams( self::$uploadParams );
    }

    /**
     * @return ServerCheck_serverParams
     */
    public function getAllServerParams(){
        return new ServerCheck_serverParams( self::$serverParams );
    }

    /**
     * @return ServerCheck_mysqlParams
     */
    public function getMysqlParams(){
        return new ServerCheck_mysqlParams( $this->_getLazyCachedMysqlConfParams() );
    }

    protected function _getLazyCachedMysqlConfParams(){

        if( self::$MysqlParams === null ){

            self::$MysqlParams  = new ServerCheck_params();

            $db = Database::obtain();
            $queryMysqlVariables = "show variables";
            $stmt = $db->getConnection()->query( $queryMysqlVariables );
            $stmt->setFetchMode( PDO::FETCH_ASSOC );
            $stmt->execute();
            $variables = $stmt->fetchAll();
            foreach ( $variables as $key => $value ){
                $_VAR_NAME = $value['Variable_name'];

                if(isset(self::$MysqlParams->$_VAR_NAME)){
                    self::$MysqlParams->$_VAR_NAME = $value['Value'];
                }
            }

            self::$serverParams->mysql_params = new ServerCheck_mysqlParams( self::$MysqlParams );

        }

        return self::$MysqlParams;

    }

} 