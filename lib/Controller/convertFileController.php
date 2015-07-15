<?php

set_time_limit( 0 );

class convertFileController extends ajaxController {

    protected $file_name;
    protected $source_lang;
    protected $target_lang;
    protected $segmentation_rule;

    protected $cache_days = 10;

    protected $intDir;
    protected $errDir;

    protected $cookieDir;

    //this will prevent recursion loop when ConvertFileWrapper will call the doAction()
    protected $convertZipFile = true;

    public function __construct() {

        parent::__construct();

        $filterArgs = array(
                'file_name'         => array(
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW // | FILTER_FLAG_STRIP_HIGH
                ),
                'source_lang'       => array(
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'target_lang'       => array(
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'segmentation_rule' => array(
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                )
        );

        $postInput = filter_input_array( INPUT_POST, $filterArgs );

        $this->file_name         = $postInput[ 'file_name' ];
        $this->source_lang       = $postInput[ "source_lang" ];
        $this->target_lang       = $postInput[ "target_lang" ];
        $this->segmentation_rule = $postInput[ "segmentation_rule" ];

        if ( $this->segmentation_rule == "" ) {
            $this->segmentation_rule = null;
        }

        $this->cookieDir = $_COOKIE[ 'upload_session' ];
        $this->intDir    = INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $this->cookieDir;
        $this->errDir    = INIT::$STORAGE_DIR . DIRECTORY_SEPARATOR . 'conversion_errors' . DIRECTORY_SEPARATOR . $this->cookieDir;

    }

    public function doAction() {

        $this->result[ 'code' ] = 0; // No Good, Default

        if ( empty( $this->file_name ) ) {
            $this->result[ 'code' ]      = -1; // No Good, Default
            $this->result[ 'errors' ][ ] = array( "code" => -1, "message" => "Error: missing file name." );
            return false;
        }

        $ext = FilesStorage::pathinfo_fix( $this->file_name, PATHINFO_EXTENSION );

        $conversionHandler = new ConversionHandler();
        $conversionHandler->setFileName( $this->file_name );
        $conversionHandler->setSourceLang( $this->source_lang );
        $conversionHandler->setTargetLang( $this->target_lang );
        $conversionHandler->setSegmentationRule( $this->segmentation_rule );
        $conversionHandler->setCookieDir( $this->cookieDir );
        $conversionHandler->setIntDir( $this->intDir );
        $conversionHandler->setErrDir( $this->errDir );

        if ( $ext == "zip" ) {
            if ( $this->convertZipFile ) {
                $fileObjects = $conversionHandler->extractZipFile();
                //call convertFileWrapper and start conversions for each file

                $realFileObjectInfo = $fileObjects;
                $realFileObjectNames = array_map(
                        array('ZipArchiveExtended', 'getFileName'),
                        $fileObjects
                );

                foreach($realFileObjectNames as $i => &$fileObject){
                    $__fileName = $fileObject;
                    $__realFileName = $realFileObjectInfo[$i];
                    $filesize = filesize($this->intDir . DIRECTORY_SEPARATOR . $__realFileName);

                    $fileObject = array(
                        'name' => $__fileName,
                        'size' => $filesize
                    );
                    $realFileObjectInfo[$i] = $fileObject;
                }

                $this->result['data']['zipFiles'] = json_encode($realFileObjectNames);

                $stdFileObjects = array();

                if($fileObjects !== null) {
                    foreach ( $fileObjects as $fName ) {

                        $newStdFile        = new stdClass();
                        $newStdFile->name  = $fName;
                        $stdFileObjects[ ] = $newStdFile;

                    }
                }
                else{
                    $errors = $conversionHandler->getResult();
                    $errors = array_map(array('Upload','formatExceptionMessage'), $errors['errors']);

                    $this->result['errors'] = array_merge($this->result['errors'], $errors);
                    return false;
                }

                /* Do conversions here */
                $converter              = new ConvertFileWrapper( $stdFileObjects, false );
                $converter->intDir      = $this->intDir;
                $converter->errDir      = $this->errDir;
                $converter->cookieDir   = $this->cookieDir;
                $converter->source_lang = $this->source_lang;
                $converter->target_lang = $this->target_lang;
                $converter->doAction();

                $errors = $converter->checkResult();
                if(count($errors) > 0){
                    $this->result['errors'] = array_merge($this->result['errors'], $errors);
                }

            } else {
                $this->result[ 'errors' ][ ] = array( "code" => -2, "message" => "Nested zip files are not allowed" );

                return false;
            }
        } else {
            $conversionHandler->doAction();

            $this->result = $conversionHandler->getResult();

            if ( $this->result['code'] < 0 ) {
                $this->result;
            }

        }

        ( isset( $this->result['errors'] ) ) ? null : $this->result['errors'] = array();

        if(count($this->result['errors']) == 0) {
            $this->result['code'] = 1;
        }
    }

}
