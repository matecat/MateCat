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
class Upload  {

    protected $dirUpload;
    protected $acceptedMime = array();
    protected $acceptedExtensions = array();

    protected $uploadToken;

    public function getDirUploadToken(){
        return $this->uploadToken;
    }

    public function getUploadPath(){
        return $this->dirUpload;
    }

    public function __construct(){

        $this->uploadToken =  Utils::create_guid( 'API' );

        $this->dirUpload = INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $this->uploadToken;

        if( !file_exists( $this->dirUpload ) ){
            mkdir( $this->dirUpload, 0775 );
        }

        //Mime White List, take them from ProjectManager.php
        $this->acceptedMime = array();

        //flatten to one dimensional list of keys
        foreach( INIT::$SUPPORTED_FILE_TYPES as $extensions ){
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

        foreach( $filesToUpload as $inputName => $file ) {
            $result->$inputName = $this->_uploadFile($file);
        }

        return $result;
    }

    /**
     *
     * Upload File from $_FILES
     * $RegistryKeyIndex MUST BE form name Element
     *
     * @param $fileUp
     * @return string|null
     * @throws Exception
     */
    protected function _uploadFile($fileUp) {

        $mod_name = null;

        if (empty ($fileUp)) {
            throw new Exception ( __METHOD__ . " -> File Not Found In Registry Instance.");
        }

        $fileName    = $fileUp[ 'name' ];
        $fileTmpName = $fileUp[ 'tmp_name' ];
//        $fileType    = $fileUp[ 'type' ];
        $fileError   = $fileUp[ 'error' ];
        $fileSize    = $fileUp[ 'size' ];

        $fileUp = (object)$fileUp;

        if ( !empty ($fileError) ) {

            switch ($fileError) {
                case 1 : //UPLOAD_ERR_INI_SIZE
                    throw new Exception ( __METHOD__ . " -> The file '$fileName' is bigger than this PHP installation allows.");
                    break;
                case 2 : //UPLOAD_ERR_FORM_SIZE
                    throw new Exception ( __METHOD__ . " -> The file '$fileName' is bigger than this form allows.");
                    break;
                case 3 : //UPLOAD_ERR_PARTIAL
                    throw new Exception ( __METHOD__ . " -> Only part of the file '$fileName'  was uploaded.");
                    break;
                case 4 : //UPLOAD_ERR_NO_FILE
                    throw new Exception ( __METHOD__ . " -> No file was uploaded.");
                    break;
                case 6 : //UPLOAD_ERR_NO_TMP_DIR
                    throw new Exception ( __METHOD__ . " -> Missing a temporary folder. ");
                    break;
                case 7 : //UPLOAD_ERR_CANT_WRITE
                    throw new Exception ( __METHOD__ . " -> Failed to write file to disk.");
                    break;
                case 8 : //UPLOAD_ERR_EXTENSION
                    throw new Exception ( __METHOD__ . " -> A PHP extension stopped the file upload. PHP does not provide a way to ascertain which extension caused the file upload to stop; examining the list of loaded extensions with phpinfo() may help.");
                    break;
                default:
                    throw new Exception ( __METHOD__ . " -> Unknown Error.");
                    break;
            }

        } else {

            if ( !$this->_isRightExtension( $fileUp ) ) {
                throw new Exception ( __METHOD__ . " -> File Extension Not Allowed. '$fileName'" );
            }

            if (!$this->_isRightMime( $fileUp )) {
                throw new Exception ( __METHOD__ . " -> File Mime Not Allowed. '$fileName'");
            }

            if ($fileSize >= INIT::$MAX_UPLOAD_FILE_SIZE ) {
                throw new Exception ( __METHOD__ . " -> File Dimensions Not Allowed. '$fileName'");
            }

            //All Right!!! GO!!!
            $mod_name = self::_fixFileName( $fileName );
            if ( !move_uploaded_file( $fileTmpName, $this->dirUpload . DIRECTORY_SEPARATOR . $mod_name ) ) {
                throw new Exception ( __METHOD__ . " -> Failed To Store File '$fileName' On Server." );
            }

            // octal; changing mode
            if ( !chmod( $this->dirUpload . DIRECTORY_SEPARATOR . $mod_name, 0664 ) ) {
                throw new Exception ( __METHOD__ . " -> Failed To Set Permissions On File. '$fileName'" );
            }

        }

        $fileUp->name = $mod_name;
        $fileUp->file_path = $this->dirUpload . DIRECTORY_SEPARATOR . $mod_name;
        unset($fileUp->tmp_name);

        return $fileUp;

    }


    /**
     *
     * Remove Un-Wanted Chars from string name
     * @param (string) $string
     * @return string
     */
    protected static function _fixFileName($string) {
        $string = filter_var( $string, FILTER_SANITIZE_STRING, array( 'flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW ) );
        $string = preg_replace ( '/[^\pL0-9\040\.\-\=_]/u', '', $string ); //strips whitespace and odd chars
        $string = preg_replace ( '/[\040]+/', '_', $string ); //strips whitespace and odd chars
        return $string;
    }

    /**
     * Check Mime For Wanted Mime accordingly to $this->setMime
     *
     * @param $fileUp
     * @return bool
     */
    protected function _isRightMime( $fileUp ) {

        //if empty accept ALL File Types
        if (empty ( $this->acceptedMime )) {
            return true;
        }

        foreach ( $this->acceptedMime as $this_mime ) {
            if ( strpos ( $fileUp->type, $this_mime ) !== false) {
                return true;
            }
        }
        return false;
    }

    protected function _isRightExtension( $fileUp ){

        $fileNameChunks = explode( ".", $fileUp->name );

        //first Check the extension
        if( !array_key_exists( strtolower( $fileNameChunks[count($fileNameChunks) - 1] ), $this->acceptedExtensions ) ){
            return false;
        }

        return true;
    }

}