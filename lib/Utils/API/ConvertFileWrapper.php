<?php

/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 28/01/14
 * Time: 19.56
 *
 */
class ConvertFileWrapper extends convertFileController {

    public $file_name;
    public $source_lang;
    public $target_lang;
    public $intDir;
    public $errDir;
    public $cookieDir;

    protected $fileStruct;
    protected $resultStack = array();

    public function __construct( $stdResult, $convertZipFile = true ) {
        $this->fileStruct     = $stdResult;
        $this->convertZipFile = $convertZipFile;
        $this->readLoginInfo();
    }

    public function setUser( Users_UserStruct $user = null ){
        $this->user = new Users_UserStruct();
        $this->userIsLogged = false;
        if( !empty( $user ) ){
            $this->user = $user;
            $this->userIsLogged = true;
        }
    }

    public function doAction() {

        foreach ( $this->fileStruct as $_file ) {
            $this->file_name = $_file->name;
            parent::doAction();
            $this->resultStack[ ] = $this->result;
        }

    }

    /**
     * Check on executed conversion results
     *
     * @throws Exception
     */
    public function checkResult() {

//        Log::doLog( $this->resultStack );

        $failure = false;
        foreach ( $this->resultStack as $res ) {
            if ( $res[ 'code' ] <= 0 ) {
                $failure = true;
            }
        }

        $result = array( 'errors' => array() );
        if ( $failure ) {
            $result = end( $this->resultStack );
        }
        $this->resultStack = $result[ 'errors' ];

        return $this->resultStack;

    }

} 