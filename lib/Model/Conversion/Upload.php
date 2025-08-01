<?php

namespace Conversion;

use Conversion\MimeTypes\MimeTypes;
use Exception;
use INIT;
use stdClass;
use Utils;

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

    protected string $dirUpload;

    protected string $uploadToken;

    protected bool $raiseException = true;

    public function getDirUploadToken() {
        return $this->uploadToken;
    }

    public function getUploadPath(): string {
        return $this->dirUpload;
    }

    /**
     * @param boolean $raiseException
     */
    public function setRaiseException( bool $raiseException ) {
        $this->raiseException = $raiseException;
    }


    /**
     * @throws Exception
     */
    public function __construct( $uploadToken = null ) {

        if ( empty( $uploadToken ) ) {
            $this->uploadToken = Utils::uuid4();
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
     * @param array $filesToUpload
     *
     * @return stdClass
     * @throws Exception
     */
    public function uploadFiles( array $filesToUpload ): stdClass {

        $result = new stdClass();

        if ( empty( $filesToUpload ) ) {
            throw new Exception ( "No files received." );
        }

        if ( $this->_filesAreTooMuch( $filesToUpload ) ) {
            throw new Exception ( "Too much files uploaded. Maximum value is " . INIT::$MAX_NUM_FILES );
        }

        $uploadStruct = static::getUniformGlobalFilesStructure( $filesToUpload );
        foreach ( $uploadStruct as $inputName => $file ) {
            $result->{$inputName} = $this->_uploadFile( $file );
        }

        return $result;
    }

    public static function getUniformGlobalFilesStructure( array $filesToUpload ): stdClass {

        $result = new stdClass();
        foreach ( $filesToUpload as $inputName => $file ) {

            if ( isset( $file[ 'tmp_name' ] ) && is_array( $file[ 'tmp_name' ] ) ) {

                $_file = [];
                foreach ( $file[ 'tmp_name' ] as $index => $value ) {
                    $_file[ 'tmp_name' ]            = $file[ 'tmp_name' ][ $index ];
                    $_file[ 'name' ]                = $file[ 'name' ][ $index ];
                    $_file[ 'size' ]                = $file[ 'size' ][ $index ];
                    $_file[ 'type' ]                = $file[ 'type' ][ $index ];
                    $_file[ 'error' ]               = $file[ 'error' ][ $index ];
                    $result->{$_file[ 'tmp_name' ]} = $_file;
                }

            } else {
                $result->$inputName = $file;
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
    protected function _uploadFile( $fileUp ): object {

        if ( empty ( $fileUp ) ) {
            throw new Exception ( __METHOD__ . " -> File Not Found In Registry Instance." );
        }

        // fix possibly XSS on the file name
        $fileUp[ 'name' ] = $this->fixFileName( $fileUp[ 'name' ] );

        $fileName    = $fileUp[ 'name' ];
        $fileTmpName = $fileUp[ 'tmp_name' ];
        $fileType    = $fileUp[ 'type' ] = ( new MimeTypes() )->guessMimeType( $fileUp[ 'tmp_name' ] );
        $fileError   = $fileUp[ 'error' ];
        $fileSize    = $fileUp[ 'size' ];

        $out_filename = ZipArchiveHandler::getFileName( $fileName );

        if ( $fileSize == 0 ) {
            throw new Exception ( "The file '$out_filename' is empty." );
        }

        $fileUp = (object)$fileUp;


        if ( !empty ( $fileError ) ) {

            switch ( $fileError ) {
                case 1 : //UPLOAD_ERR_INI_SIZE
                    $this->setObjectErrorOrThrowException(
                            $fileUp,
                            new Exception ( "The file '$out_filename' is bigger than this PHP installation allows." )
                    );
                    break;
                case 2 : //UPLOAD_ERR_FORM_SIZE
                    $this->setObjectErrorOrThrowException(
                            $fileUp,
                            new Exception ( "The file '$out_filename' is bigger than this form allows." )
                    );
                    break;
                case 3 : //UPLOAD_ERR_PARTIAL
                    $this->setObjectErrorOrThrowException(
                            $fileUp,
                            new Exception ( "Only part of the file '$out_filename'  was uploaded." )
                    );
                    break;
                case 4 : //UPLOAD_ERR_NO_FILE
                    $this->setObjectErrorOrThrowException(
                            $fileUp,
                            new Exception ( "No file was uploaded." )
                    );
                    break;
                case 6 : //UPLOAD_ERR_NO_TMP_DIR
                    $this->setObjectErrorOrThrowException(
                            $fileUp,
                            new Exception ( "Missing a temporary folder. " )
                    );
                    break;
                case 7 : //UPLOAD_ERR_CANT_WRITE
                    $this->setObjectErrorOrThrowException(
                            $fileUp,
                            new Exception ( "Failed to write file to disk." )
                    );
                    break;
                case 8 : //UPLOAD_ERR_EXTENSION
                    $this->setObjectErrorOrThrowException(
                            $fileUp,
                            new Exception ( "A PHP extension stopped the file upload. PHP does not provide a way to ascertain which extension caused the file upload to stop; examining the list of loaded extensions with phpinfo() may help." )
                    );
                    break;
                default:
                    $this->setObjectErrorOrThrowException(
                            $fileUp,
                            new Exception ( "Unknown Error: $fileError" )
                    );
                    break;
            }

        } else {

            if ( $fileType !== null ) {

                if ( !$this->_isRightMime( $fileUp ) ) {
                    $this->setObjectErrorOrThrowException(
                            $fileUp,
                            new Exception ( "File format not supported. '" . $out_filename . "'" )
                    );
                }

            }

            if ( !$this->_isRightExtension( $fileUp ) ) {
                $this->setObjectErrorOrThrowException(
                        $fileUp,
                        new Exception ( "File Extension Not Allowed. '" . $out_filename . "'" )
                );

            }

            // NOTE FOR ZIP FILES
            //This exception is already raised by ZipArchiveExtended when file is unzipped.

            $filePathInfo = pathinfo( $out_filename );
            $fileMaxSize  = ( $filePathInfo[ 'extension' ] === 'tmx' ) ? INIT::$MAX_UPLOAD_TMX_FILE_SIZE : INIT::$MAX_UPLOAD_FILE_SIZE;

            if ( $fileSize >= $fileMaxSize ) {
                $this->setObjectErrorOrThrowException(
                        $fileUp,
                        new Exception ( "File Dimensions Not Allowed. '$out_filename'" )
                );
            }

            if ( !Utils::isValidFileName( $fileUp->name ) ) {
                $this->setObjectErrorOrThrowException(
                        $fileUp,
                        new Exception ( "Invalid File Name '" . $out_filename . "'" )
                );
            }

            //Exit on Error
            if ( !empty( $fileUp->error ) ) {
                @unlink( $fileTmpName );

                return $fileUp;
            }

            //All Right!!! GO!!!
            if ( !copy( $fileTmpName, $this->dirUpload . DIRECTORY_SEPARATOR . $fileUp->name ) ) {
                $this->setObjectErrorOrThrowException(
                        $fileUp,
                        new Exception ( "Failed To Store File '$out_filename' On Server." )
                );
            }

            //In Unix you can't rename or move between filesystems,
            //Instead you must copy the file from one source location to the destination location, then delete the source.
            @unlink( $fileTmpName );

            // octal; changing mode
            if ( !chmod( $this->dirUpload . DIRECTORY_SEPARATOR . $fileUp->name, 0664 ) ) {
                $this->setObjectErrorOrThrowException(
                        $fileUp,
                        new Exception ( "Failed To Set Permissions On File. '$out_filename'" )
                );
            }

        }

        $fileUp->file_path = $this->dirUpload . DIRECTORY_SEPARATOR . $fileUp->name;
        unset( $fileUp->tmp_name );

        return $fileUp;

    }

    /**
     * Fixes the file name by appending a unique suffix and adjusting the path.
     *
     * @param string $stringName The original file name.
     * @param bool   $upCount    Optional. Whether to include a counter in the file name suffix. Defaults to true.
     *
     * @return string The fixed file name with the adjusted path.
     */
    public function fixFileName( string $stringName, bool $upCount = true ): string {
        return Utils::fixFileName( $stringName, $this->dirUpload, $upCount );
    }

    /**
     * Checks if the number of files exceeds the maximum limit.
     *
     * @param array $filesToUpload The array of files to upload.
     *
     * @return bool Returns true if the number of files exceeds the maximum limit,
     * false otherwise.
     */
    protected function _filesAreTooMuch( array $filesToUpload ): bool {

        $count = 0;
        foreach ( $filesToUpload as $value ) {
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
     * @param object $fileUp
     *
     * @return bool
     */
    protected function _isRightMime( object $fileUp ): bool {

        //Mime White List, take them from ProjectManager.php
        foreach ( INIT::$MIME_TYPES as $key => $value ) {
            if ( strpos( $key, $fileUp->type ) !== false ) {
                return true;
            }
        }

        return false;

    }

    /**
     * Checks if the file extension is allowed.
     *
     * @param object $fileUp The uploaded file object.
     *
     * @return bool Returns true if the file extension is allowed, false otherwise.
     */
    protected function _isRightExtension( object $fileUp ): bool {

        $acceptedExtensions = [];
        foreach ( INIT::$SUPPORTED_FILE_TYPES as $value2 ) {
            $acceptedExtensions = array_unique( array_merge( $acceptedExtensions, array_keys( $value2 ) ) );
        }

        $fileNameChunks = explode( ".", $fileUp->name );

        //first Check the extension
        if ( in_array( strtolower( $fileNameChunks[ count( $fileNameChunks ) - 1 ] ), $acceptedExtensions ) ) {
            return true;
        }

        return false;
    }

    /**
     * @param object    $fileUp
     * @param Exception $exn
     *
     * @return void
     * @throws Exception
     */
    private function setObjectErrorOrThrowException( object $fileUp, Exception $exn ) {
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

