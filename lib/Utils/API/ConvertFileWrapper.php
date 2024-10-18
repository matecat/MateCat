<?php

use Conversion\ConvertedFileModel;

/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 28/01/14
 * Time: 19.56
 *
 */
class ConvertFileWrapper extends convertFileController {

    public $intDir;
    public $errDir;
    protected $fileStruct;
    protected $resultStack = [];

    public function __construct( $stdResult, $convertZipFile = true ) {
        $this->fileStruct     = $stdResult;
        $this->convertZipFile = (bool)$convertZipFile;
        $this->identifyUser();
    }

    public function setUser( Users_UserStruct $user = null ) {
        $this->user         = new Users_UserStruct();
        $this->userIsLogged = false;
        if ( !empty( $user ) ) {
            $this->user         = $user;
            $this->userIsLogged = true;
        }
    }

    public function doAction() {

        foreach ( $this->fileStruct as $_file ) {
            $this->file_name = $_file->name;
            parent::doAction();
            $this->resultStack[] = $this->result;
        }

    }

    /**
     * Check on executed conversion results
     * @return ConvertedFileModel
     */
    public function checkResult() {

        $failure = false;

        /** @var ConvertedFileModel $res */
        foreach ( $this->resultStack as $res ) {
            if ( $res->hasAnErrorCode() ) {
                $failure = true;
            }
        }

        if ( $failure ) {
            return end( $this->resultStack );
        }

        return null;

    }

} 