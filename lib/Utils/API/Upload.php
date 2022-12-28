<?php

/**
 *
 * @author Domenico Lupinetti - Ostico@gmail.com
 *
 * @example
 * <pre>
 *   if( \Registry::getInstance ()->get ( "HttpRequest", 'requestMethod' ) == 'POST' ) {
 *       $uploadInstance = new Upload();
 *       $uploadInstance->uploadFiles( $_FILES );
 *   }
 * </pre>
 *
 */
class Upload {

    protected $dirUpload;

    protected $uploadToken;

    protected $raiseException = true;

    public function getDirUploadToken() {
        return $this->uploadToken;
    }

    public function getUploadPath() {
        return $this->dirUpload;
    }

    /**
     * @param boolean $raiseException
     */
    public function setRaiseException( $raiseException ) {
        $this->raiseException = $raiseException;
    }


    public function __construct( $uploadToken = null ) {

        if ( empty( $uploadToken ) ) {
            $this->uploadToken = Utils::createToken( 'API' );
        } else {
            $this->uploadToken = $uploadToken;
        }

        $this->dirUpload = INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $this->uploadToken;

        if ( !file_exists( $this->dirUpload ) ) {
            mkdir( $this->dirUpload, 0775 );
        }

    }

    /**
     * Start loading instance
     *
     * @param $filesToUpload
     *
     * @return stdClass
     * @throws Exception
     */
    public function uploadFiles( $filesToUpload ) {

        $result = new stdClass();

        if ( empty( $filesToUpload ) ) {
            throw new Exception ( "No files received." );
        }

        if ( $this->_filesAreTooMuch( $filesToUpload ) ) {
            throw new Exception ( "Too much files uploaded. Maximum value is " . INIT::$MAX_NUM_FILES );
        }

        foreach ( $filesToUpload as $inputName => $file ) {

            if ( isset( $file[ 'tmp_name' ] ) && is_array( $file[ 'tmp_name' ] ) ) {

                $_file = [];
                foreach ( $file[ 'tmp_name' ] as $index => $value ) {
                    $_file[ 'tmp_name' ]            = $file[ 'tmp_name' ][ $index ];
                    $_file[ 'name' ]                = $file[ 'name' ][ $index ];
                    $_file[ 'size' ]                = $file[ 'size' ][ $index ];
                    $_file[ 'type' ]                = $file[ 'type' ][ $index ];
                    $_file[ 'error' ]               = $file[ 'error' ][ $index ];
                    $result->{$_file[ 'tmp_name' ]} = $this->_uploadFile( $_file );
                }

            } else {
                $result->$inputName = $this->_uploadFile( $file );
            }

        }

        return $result;
    }

    /**
     * Upload File from $_FILES
     * $RegistryKeyIndex MUST BE form name Element
     *
     * @param $fileUp
     *
     * @return object
     * @throws Exception
     */
    protected function _uploadFile( $fileUp ) {

        $mod_name = null;

        if ( empty ( $fileUp ) ) {
            throw new Exception ( __METHOD__ . " -> File Not Found In Registry Instance." );
        }

        $fileName    = $fileUp[ 'name' ];
        $fileTmpName = $fileUp[ 'tmp_name' ];
        $fileType    = $fileUp[ 'type' ] = mime_content_type( $fileUp[ 'tmp_name' ] );
        $fileError   = $fileUp[ 'error' ];
        $fileSize    = $fileUp[ 'size' ];

        $fileUp = (object)$fileUp;


        if ( !empty ( $fileError ) ) {

            switch ( $fileError ) {
                case 1 : //UPLOAD_ERR_INI_SIZE
                    $this->setObjectErrorOrThrowException(
                            $fileUp,
                            new Exception ( __METHOD__ . " -> The file '$fileName' is bigger than this PHP installation allows." )
                    );
                    break;
                case 2 : //UPLOAD_ERR_FORM_SIZE
                    $this->setObjectErrorOrThrowException(
                            $fileUp,
                            new Exception ( __METHOD__ . " -> The file '$fileName' is bigger than this form allows." )
                    );
                    break;
                case 3 : //UPLOAD_ERR_PARTIAL
                    $this->setObjectErrorOrThrowException(
                            $fileUp,
                            new Exception ( __METHOD__ . " -> Only part of the file '$fileName'  was uploaded." )
                    );
                    break;
                case 4 : //UPLOAD_ERR_NO_FILE
                    $this->setObjectErrorOrThrowException(
                            $fileUp,
                            new Exception ( __METHOD__ . " -> No file was uploaded." )
                    );
                    break;
                case 6 : //UPLOAD_ERR_NO_TMP_DIR
                    $this->setObjectErrorOrThrowException(
                            $fileUp,
                            new Exception ( __METHOD__ . " -> Missing a temporary folder. " )
                    );
                    break;
                case 7 : //UPLOAD_ERR_CANT_WRITE
                    $this->setObjectErrorOrThrowException(
                            $fileUp,
                            new Exception ( __METHOD__ . " -> Failed to write file to disk." )
                    );
                    break;
                case 8 : //UPLOAD_ERR_EXTENSION
                    $this->setObjectErrorOrThrowException(
                            $fileUp,
                            new Exception ( __METHOD__ . " -> A PHP extension stopped the file upload. PHP does not provide a way to ascertain which extension caused the file upload to stop; examining the list of loaded extensions with phpinfo() may help." )
                    );
                    break;
                default:
                    $this->setObjectErrorOrThrowException(
                            $fileUp,
                            new Exception ( __METHOD__ . " -> Unknown Error: $fileError" )
                    );
                    break;
            }

        } else {

            $out_filename = ZipArchiveExtended::getFileName( $fileName );

            if ( $fileType !== null ) {

                if ( !$this->_isRightMime( $fileUp ) ) {
                    $this->setObjectErrorOrThrowException(
                            $fileUp,
                            new Exception ( __METHOD__ . " -> Mime type Not Allowed. '" . $out_filename . "'" )
                    );
                }

            }

            if ( !$this->_isRightExtension( $fileUp ) ) {
                $this->setObjectErrorOrThrowException(
                        $fileUp,
                        new Exception ( __METHOD__ . " -> File Extension Not Allowed. '" . $out_filename . "'" )
                );

            }

            // NOTE FOR ZIP FILES
            //This exception is already raised by ZipArchiveExtended when file is unzipped.

            $filePathInfo = pathinfo($fileName);
            $fileMaxSize = ($filePathInfo['extension'] === 'tmx') ? INIT::$MAX_UPLOAD_TMX_FILE_SIZE : INIT::$MAX_UPLOAD_FILE_SIZE;

            if ( $fileSize >= $fileMaxSize ) {
                $this->setObjectErrorOrThrowException(
                        $fileUp,
                        new Exception ( __METHOD__ . " -> File Dimensions Not Allowed. '$out_filename'" )
                );
            }

            $mod_name = $this->fixFileName( $fileUp->name );

            if ( !Utils::isValidFileName( $mod_name ) ) {
                $this->setObjectErrorOrThrowException(
                        $fileUp,
                        new Exception ( __METHOD__ . " -> Invalid File Name '" . ZipArchiveExtended::getFileName( $fileUp->name ) . "'" )
                );
            }

            //Exit on Error
            if ( !empty( $fileUp->error ) ) {
                @unlink( $fileTmpName );

                return $fileUp;
            }

            //All Right!!! GO!!!
            if ( !copy( $fileTmpName, $this->dirUpload . DIRECTORY_SEPARATOR . $mod_name ) ) {
                $this->setObjectErrorOrThrowException(
                        $fileUp,
                        new Exception ( __METHOD__ . " -> Failed To Store File '$out_filename' On Server." )
                );
            }

            //In Unix you can't rename or move between filesystems,
            //Instead you must copy the file from one source location to the destination location, then delete the source.
            @unlink( $fileTmpName );

            // octal; changing mode
            if ( !chmod( $this->dirUpload . DIRECTORY_SEPARATOR . $mod_name, 0664 ) ) {
                $this->setObjectErrorOrThrowException(
                        $fileUp,
                        new Exception ( __METHOD__ . " -> Failed To Set Permissions On File. '$out_filename'" )
                );
            }

        }

        $fileUp->name      = $mod_name;
        $fileUp->file_path = $this->dirUpload . DIRECTORY_SEPARATOR . $mod_name;
        unset( $fileUp->tmp_name );

        return $fileUp;

    }

    /**
     *
     * Remove Un-Wanted Chars from string name
     *
     * @param (string) $string
     *
     * @return string
     * @throws Exception
     */
    public function fixFileName( $stringName, $upCount = true ) {
        return Utils::fixFileName( $stringName, $this->dirUpload, $upCount );
    }

    protected function _filesAreTooMuch( $filesToUpload ) {

        $count = 0;
        foreach ( $filesToUpload as $key => $value ) {
            if ( is_array( $value[ 'tmp_name' ] ) ) {
                $count += count( $value[ 'tmp_name' ] );
            } else {
                $count++;
            }
        }

        return $count > INIT::$MAX_NUM_FILES;

    }

    /**
     * Check Mime For Wanted Mime accordingly to $this->setMime
     *
     * @param $fileUp
     *
     * @return bool
     */
    protected function _isRightMime( $fileUp ) {

        //Mime White List, take them from ProjectManager.php
        foreach ( INIT::$MIME_TYPES as $key => $value ) {
            if ( strpos( $key, $fileUp->type ) !== false ) {
                return true;
            }
        }

        return false;

    }

    protected function _isRightExtension( $fileUp ) {

        $acceptedExtensions = [];
        foreach ( INIT::$SUPPORTED_FILE_TYPES as $key2 => $value2 ) {
            $acceptedExtensions = array_unique( array_merge( $acceptedExtensions, array_keys( $value2 ) ) );
        }

        $fileNameChunks = explode( ".", $fileUp->name );

        //first Check the extension
        if ( array_search( strtolower( $fileNameChunks[ count( $fileNameChunks ) - 1 ] ), $acceptedExtensions ) !== false ) {
            return true;
        }

        return false;
    }

    public static function formatExceptionMessage( $errorArray ) {
        //The message format is: __METHOD__ -> <message>.
        //The client output should be just <message>
        $msg = $errorArray[ 'message' ];
        if ( strpos( $msg, " -> " ) !== false ) {
            $msg                     = explode( " -> ", $msg );
            $errorArray[ 'message' ] = $msg[ 1 ];
        } else {
            $errorArray[ 'message' ] = $msg;
        }

        return $errorArray;
    }

    private function setObjectErrorOrThrowException( $fileUp, Exception $exn ) {
        if ( $this->raiseException ) {
            throw $exn;
        } else {
            $fileUp->error = [
                    'code'    => $exn->getCode(),
                    'message' => $exn->getMessage()
            ];
        }
    }
}

