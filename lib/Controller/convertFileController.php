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
    protected $lang_handler;

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

        $this->readLoginInfo();

    }

    public function doAction() {

        $this->result[ 'code' ] = 0; // No Good, Default

        $this->lang_handler = Langs_Languages::getInstance();
        $this->validateSourceLang();
        $this->validateTargetLangs();

        if ( empty( $this->file_name ) ) {
            $this->result[ 'code' ]     = -1; // No Good, Default
            $this->result[ 'errors' ][] = array( "code" => -1, "message" => "Error: missing file name." );
        }

        if( !empty( $this->result[ 'errors' ] ) ){
            return false;
        }

        if ( $this->isLoggedIn() ) {
            $this->featureSet->loadFromUserEmail( $this->user->email ) ;
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
        $conversionHandler->setFeatures( $this->featureSet );
        $conversionHandler->setUserIsLogged( $this->userIsLogged );

        if ( $ext == "zip" ) {
            if ( $this->convertZipFile ) {
                $this->handleZip( $conversionHandler );
            } else {
                $this->result[ 'errors' ][] = array(
                        "code"    => -2,
                        "message" => "Nested zip files are not allowed"
                );

                return false;
            }
        } else {
            $conversionHandler->doAction();

            $this->result = $conversionHandler->getResult();

        }

        ( isset( $this->result[ 'errors' ] ) ) ? null : $this->result[ 'errors' ] = array();

        if ( count( $this->result[ 'errors' ] ) == 0 ) {
            $this->result[ 'code' ] = 1;
        } else {
            $this->result[ 'errors' ] = array_values( $this->result[ 'errors' ] );
        }
    }

    private function validateSourceLang() {
        try {
            $this->lang_handler->validateLanguage( $this->source_lang );
        } catch ( Exception $e ) {
            $this->result[ 'errors' ][]    = [ "code" => -3, "message" => $e->getMessage() ];
        }
    }

    private function validateTargetLangs() {
        $targets = explode( ',', $this->target_lang );
        $targets = array_map( 'trim', $targets );
        $targets = array_unique( $targets );

        if ( empty( $targets ) ) {
            $this->result[ 'errors' ][]    = [ "code" => -4, "message" => "Missing target language." ];
        }

        try {

            foreach ( $targets as $target ) {
                $this->lang_handler->validateLanguage( $target );
            }

        } catch ( Exception $e ) {
            $this->result[ 'errors' ][]    = [ "code" => -4, "message" => $e->getMessage() ];
        }

        $this->target_lang = implode( ',', $targets );
    }

    private function handleZip( ConversionHandler $conversionHandler ) {
        
      // this makes the conversionhandler accumulate eventual errors on files and continue
      $conversionHandler->setStopOnFileException( false );

      $internalZipFileNames = $conversionHandler->extractZipFile();
      //call convertFileWrapper and start conversions for each file

      if ( $conversionHandler->uploadError ) {
        $fileErrors = $conversionHandler->getUploadedFiles();

        foreach ( $fileErrors as $fileError ) {
          if ( count( $fileError->error ) == 0 ) {
            continue;
          }

          $brokenFileName = ZipArchiveExtended::getFileName( $fileError->name );

          /*
           * TODO
           * return error code is 2 because
           *      <=0 is for errors
           *      1   is OK
           *
           * In this case, we raise warnings, hence the return code must be a new code
           */
          $this->result[ 'code' ]                      = 2;
          $this->result[ 'errors' ][ $brokenFileName ] = array(
            'code'    => $fileError->error[ 'code' ],
            'message' => $fileError->error[ 'message' ],
            'debug' => $brokenFileName
          );
        }

      }

      $realFileNames = array_map(
        array( 'ZipArchiveExtended', 'getFileName' ),
        $internalZipFileNames
      );

      foreach ( $realFileNames as $i => &$fileObject ) {
        $fileObject               = array(
          'name' => $fileObject,
          'size' => filesize( $this->intDir . DIRECTORY_SEPARATOR . $internalZipFileNames[ $i ] )
        );
      }

      $this->result[ 'data' ][ 'zipFiles' ] = json_encode( $realFileNames );

      $stdFileObjects = array();

      if ( $internalZipFileNames !== null ) {
        foreach ( $internalZipFileNames as $fName ) {

          $newStdFile       = new stdClass();
          $newStdFile->name = $fName;
          $stdFileObjects[] = $newStdFile;

        }
      } else {
        $errors = $conversionHandler->getResult();
        $errors = array_map( array( 'Upload', 'formatExceptionMessage' ), $errors[ 'errors' ] );

        $this->result[ 'errors' ] = array_merge( $this->result[ 'errors' ], $errors );

        return false;
      }

      /* Do conversions here */
      $converter              = new ConvertFileWrapper( $stdFileObjects, false );
      $converter->intDir      = $this->intDir;
      $converter->errDir      = $this->errDir;
      $converter->cookieDir   = $this->cookieDir;
      $converter->source_lang = $this->source_lang;
      $converter->target_lang = $this->target_lang;
      $converter->featureSet  = $this->featureSet;
      $converter->doAction();

      $errors = $converter->checkResult();
      if ( count( $errors ) > 0 ) {
        $this->result[ 'code' ] = 2;
        foreach ( $errors as $__err ) {
          $brokenFileName = ZipArchiveExtended::getFileName( $__err[ 'debug' ] );

          if ( !isset( $this->result[ 'errors' ][ $brokenFileName ] ) ) {
            $this->result[ 'errors' ][ $brokenFileName ] = array(
              'code'    => $__err[ 'code' ],
              'message' => $__err[ 'message' ],
              'debug'   => $brokenFileName
            );
          }
        }
      }

    }

}
