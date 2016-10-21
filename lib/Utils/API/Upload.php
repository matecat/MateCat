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
    protected $acceptedMime = array();
    protected $acceptedExtensions = array();

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
            $this->uploadToken = Utils::create_guid( 'API' );
        } else {
            $this->uploadToken = $uploadToken;
        }

        $this->dirUpload = INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $this->uploadToken;

        if ( !file_exists( $this->dirUpload ) ) {
            mkdir( $this->dirUpload, 0775 );
        }

        //Mime White List, take them from ProjectManager.php
        foreach ( INIT::$MIME_TYPES as $key=>$value ) {
            foreach ( INIT::$SUPPORTED_FILE_TYPES as $key2 => $value2 ) {
                if (count(array_intersect(array_keys($value2), array_values($value)))>0)
                {
                    array_push($this->acceptedMime, $key);
                    break;
                }
            }
        }

        //flatten to one dimensional list of keys
        foreach ( INIT::$SUPPORTED_FILE_TYPES as $extensions ) {
            $this->acceptedExtensions += $extensions;
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

        foreach ( $filesToUpload as $inputName => $file ) {
            $result->$inputName = $this->_uploadFile( $file );
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
        $fileType    = $fileUp[ 'type' ];
        $fileError = $fileUp[ 'error' ];
        $fileSize  = $fileUp[ 'size' ];

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

            $right_mime=false;
            if($fileType!==null){

                if ( !$this->_isRightMime( $fileUp ) && (!isset($fileUp->error) || empty($fileUp->error) ) ) {
                    $right_mime=false;
                }
                else{
                    $right_mime=true;
                }
            }


            $out_filename = ZipArchiveExtended::getFileName( $fileName );
            if ( !$this->_isRightExtension( $fileUp ) && ( !isset( $fileUp->error ) || empty( $fileUp->error ) ) && !$right_mime) {
                $this->setObjectErrorOrThrowException(
                        $fileUp,
                        new Exception ( __METHOD__ . " -> File Extension and Mime type Not Allowed. '" . $out_filename . "'" )
                );

            }


            // NOTE FOR ZIP FILES
            //This exception is already raised by ZipArchiveExtended when file is unzipped.
            if ( $fileSize >= INIT::$MAX_UPLOAD_FILE_SIZE && (!isset($fileUp->error) || empty($fileUp->error) )) {
                $this->setObjectErrorOrThrowException(
                        $fileUp,
                        new Exception ( __METHOD__ . " -> File Dimensions Not Allowed. '$out_filename'" )
                );
            }

            //All Right!!! GO!!!
            $mod_name = self::fixFileName( $fileName );

            if ( (!isset($fileUp->error) || empty($fileUp->error) ) && !copy( $fileTmpName, $this->dirUpload . DIRECTORY_SEPARATOR . $mod_name )) {
                $this->setObjectErrorOrThrowException(
                        $fileUp,
                        new Exception ( __METHOD__ . " -> Failed To Store File '$out_filename' On Server." )
                );
            }

            //In Unix you can't rename or move between filesystems,
            //Instead you must copy the file from one source location to the destination location, then delete the source.
            @unlink( $fileTmpName );

            // octal; changing mode
            if ( (!isset($fileUp->error) || empty($fileUp->error) ) && !chmod( $this->dirUpload . DIRECTORY_SEPARATOR . $mod_name, 0664 ) ) {
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
     */
    public static function fixFileName( $string ) {
        //Roberto: removed STRIP_HIGH flag. Non-latin filenames are supported.
        $string = filter_var( $string, FILTER_SANITIZE_STRING, array( 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_NO_ENCODE_QUOTES) );

        //Fix Bug: Zip files, file names with contiguous whitespaces ( replaced with only one _ and not found inside the zip on download )
        $string = preg_replace( '/\p{Zs}/u', chr(0x1A), $string ); // substitute whitespaces
        $string = preg_replace( '/[^\pL0-9\.\-\=_&()\'\"\x1A]/u', '', $string ); //strips odd chars and preserve preceding placeholder
        $string = preg_replace( '/' . chr(0x1A) . '/', '_', $string ); //strips whitespace and odd chars

        return $string;
    }

    /**
     * Check Mime For Wanted Mime accordingly to $this->setMime
     *
     * @param $fileUp
     *
     * @return bool
     */
    protected function _isRightMime( $fileUp ) {

        //if empty accept ALL File Types
        if ( empty ( $this->acceptedMime ) ) {
            return true;
        }

        foreach ( $this->acceptedMime as $this_mime ) {
            if ( strpos( $fileUp->type, $this_mime ) !== false ) {
                return true;
            }
        }

        return false;
    }

    protected function _isRightExtension( $fileUp ) {

        $fileNameChunks = explode( ".", $fileUp->name );

        foreach ( INIT::$SUPPORTED_FILE_TYPES as $key => $value ) {
            foreach ( $value as $typeSupported => $value2 ) {
                if ( preg_match( '/\.' . $typeSupported . '$/i', $fileUp->type ) ) {
                    return true;
                }
            }
        }
        //first Check the extension
        if ( !array_key_exists( strtolower( $fileNameChunks[ count( $fileNameChunks ) - 1 ] ), $this->acceptedExtensions ) ) {
            return false;
        }

        return true;
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
            $fileUp->error = array(
                    'code'    => $exn->getCode(),
                    'message' => $exn->getMessage()
            );
        }
    }
}

