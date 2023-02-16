<?php

use ActivityLog\Activity;
use ActivityLog\ActivityLogStruct;
use ConnectedServices\ConnectedServiceDao;
use ConnectedServices\GDrive;
use ConnectedServices\GDriveTokenVerifyModel;
use FilesStorage\AbstractFilesStorage;
use FilesStorage\FilesStorageFactory;
use FilesStorage\S3FilesStorage;
use LQA\ChunkReviewDao;
use Matecat\SimpleS3\Client;
use Matecat\XliffParser\XliffUtils\XliffProprietaryDetect;
use XliffReplacer\XliffReplacerCallback;
use Matecat\XliffParser\Utils\Files as XliffFiles;

set_time_limit( 180 );

class downloadFileController extends downloadController {

    protected $download_type;
    protected $job;
    protected $forceXliff;
    protected $downloadToken;

    /**
     * @var GDrive\RemoteFileService
     */
    protected $remoteFileService;

    protected $openOriginalFiles;
    protected $id_file;

    protected $trereIsARemoteFile = null;

    /**
     * @var Google_Service_Drive_DriveFile
     */
    protected $remoteFiles = [];

    const FILES_CHUNK_SIZE = 3;

    public function __construct() {

        $filterArgs = [
                'filename'      => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'id_file'       => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'id_job'        => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'download_type' => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'password'      => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'downloadToken' => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'forceXliff'    => [],
                'original'      => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ]
        ];

        $__postInput = filter_var_array( $_REQUEST, $filterArgs );

        $this->_user_provided_filename = $__postInput[ 'filename' ];

        $this->id_file       = $__postInput[ 'id_file' ];
        $this->id_job        = $__postInput[ 'id_job' ];
        $this->download_type = $__postInput[ 'download_type' ];
        $this->password      = $__postInput[ 'password' ];
        $this->downloadToken = $__postInput[ 'downloadToken' ];

        $this->forceXliff        = ( isset( $__postInput[ 'forceXliff' ] ) && !empty( $__postInput[ 'forceXliff' ] ) && $__postInput[ 'forceXliff' ] == 1 );
        $this->openOriginalFiles = ( isset( $__postInput[ 'original' ] ) && !empty( $__postInput[ 'original' ] ) && $__postInput[ 'original' ] == 1 );

        if ( empty( $this->id_job ) ) {
            $this->id_job = "Unknown";
        }

        $this->featureSet = new FeatureSet();
    }

    public function doAction() {

        // get Job Info, we need only a row of jobs ( split )
        $jobData = $this->job = Jobs_JobDao::getByIdAndPassword( (int)$this->id_job, $this->password );

        // if no job was found, check if the provided password is a password_review
        if ( empty( $jobData ) ) {
            $chunkReviewStruct = ChunkReviewDao::findByReviewPasswordAndJobId( $this->password, (int)$this->id_job );
            $jobData           = $this->job = $chunkReviewStruct->getChunk();
        }

        // check for Password correctness
        if ( empty( $jobData ) ) {
            $msg = "Error : wrong password provided for download \n\n " . var_export( $_POST, true ) . "\n";
            Log::doJsonLog( $msg );
            Utils::sendErrMailReport( $msg );

            return null;
        }

        $this->project = $this->job->getProject();

        $this->featureSet->loadForProject( $this->project );

        //get storage object
        $fs        = FilesStorageFactory::create();
        $files_job = $fs->getFilesForJob( $this->id_job );

        $output_content = [];

        /*
           the procedure:
           1)original xliff file is read directly from disk; a file handler is obtained
           2)the file is read chunk by chunk by a stream parser: for each trans-unit that is encountered, target is replaced (or added) with the corresponding translation obtained from the DB
           3)the parsed portion of xliff in the buffer is flushed on temporary file
           4)the temporary file is sent to the converter and an original file is obtained
           5)the temporary file is deleted
         */

        $sDao = new Segments_SegmentDao();

        // instantiate $s3Client only if S3 is enabled
        if ( AbstractFilesStorage::isOnS3() ) {
            $s3Client = S3FilesStorage::getStaticS3Client();
        }

        //file array is chuncked. Each chunk will be used for a parallel conversion request.
        $files_job = array_chunk( $files_job, self::FILES_CHUNK_SIZE );
        foreach ( $files_job as $chunk ) {

            $files_to_be_converted = [];

            foreach ( $chunk as $file ) {

                $mime_type        = $file[ 'mime_type' ];
                $fileID           = $file[ 'id_file' ];
                $current_filename = $file[ 'filename' ];

                //get path for the output file converted to know it's right extension
                $xliffFilePath = $file[ 'xliffFilePath' ];

                $_fileName  = explode( DIRECTORY_SEPARATOR, $xliffFilePath );
                $outputPath = INIT::$TMP_DOWNLOAD . DIRECTORY_SEPARATOR . $this->id_job . DIRECTORY_SEPARATOR . $fileID . DIRECTORY_SEPARATOR . uniqid( '', true ) . "_.out." . array_pop( $_fileName );

                //make dir if doesn't exist
                if ( !file_exists( dirname( $outputPath ) ) ) {

                    Log::doJsonLog( 'Create Directory ' . escapeshellarg( dirname( $outputPath ) ) . '' );
                    mkdir( dirname( $outputPath ), 0775, true );

                }

                $data = $sDao->getSegmentsDownload( $this->job, $fileID );

                $transUnits = [];

                foreach ( $data as $i => $k ) {
                    //create a secondary indexing mechanism on segments' array; this will be useful
                    //prepend a string so non-trans unit id ( ex: numerical ) are not overwritten
                    $internalId = $k[ 'internal_id' ];

                    $transUnits[ $internalId ] [] = $i;

                    $data[ 'matecat|' . $internalId ] [] = $i;
                }

                /**
                 * Because of a bug in the filters for the cjk languages ( Exception when downloading translations )
                 * we add an hook to allow some plugins to force the conversion parameters ( languages for example )
                 * TODO: ( 25/05/2018 ) Remove when the issue will be fixed
                 */
                $_target_lang = $this->featureSet->filter(
                        'changeXliffTargetLangCode',
                        $jobData[ 'target' ]
                        , $xliffFilePath
                );

                // if Filestorage is on S3, download the file on a temp dir
                if ( AbstractFilesStorage::isOnS3() ) {
                    $s3Client            = S3FilesStorage::getStaticS3Client();
                    $params[ 'bucket' ]  = INIT::$AWS_STORAGE_BASE_BUCKET;
                    $params[ 'key' ]     = $xliffFilePath;
                    $params[ 'save_as' ] = "/tmp/" . AbstractFilesStorage::pathinfo_fix( $xliffFilePath, PATHINFO_BASENAME );
                    $s3Client->downloadItem( $params );
                    $xliffFilePath = $params[ 'save_as' ];
                }

                $fileType = XliffProprietaryDetect::getInfo( $xliffFilePath );

                // if data is empty copy the original file in the outputPath
                if(empty($data)){
                    copy( $xliffFilePath, $outputPath );
                } else {
                    // instantiate parser
                    $xsp = new \Matecat\XliffParser\XliffParser();

                    // instantiateXliffReplacerCallback
                    $xliffReplacerCallback = new XliffReplacerCallback( $this->featureSet, $this->job->source, $_target_lang );

                    // run xliff replacer
                    Log::doJsonLog( "work on " . $fileID . " " . $current_filename );
                    $setSourceInTarget = $this->download_type === 'omegat';
                    $xsp->replaceTranslation( $xliffFilePath, $data, $transUnits, $_target_lang, $outputPath, $setSourceInTarget, $xliffReplacerCallback );

                    //free memory
                    unset( $xsp );
                    unset( $data );
                }

                $output_content[ $fileID ][ 'document_content' ] = file_get_contents( $outputPath );
                $output_content[ $fileID ][ 'output_filename' ]  = $current_filename;

                if ( $this->forceXliff ) {
                    //clean the output filename by removing
                    // the unique hash identifier 55e5739b467109.05614837_.out.Test_English.doc.sdlxliff
                    $output_content[ $fileID ][ 'output_filename' ] = $current_filename .= ".xlf";

                    if ( $fileType[ 'proprietary_short_name' ] === 'matecat_converter' ) {
                        // Set the XLIFF extension to .xlf
                        // Internally, MateCat continues using .sdlxliff as default
                        // extension for the XLIFF behind the projects.
                        // Changing this behavior requires a huge refactoring that
                        // it's scheduled for future versions.
                        // We quickly fixed the behaviour from the user standpoint
                        // using the following line of code, that changes the XLIFF's
                        // extension just a moment before it is downloaded by the user.
                        $output_content[ $fileID ][ 'output_filename' ] = preg_replace( "|\\.sdlxliff$|i", ".xlf", $output_content[ $fileID ][ 'output_filename' ] );
                        $output_content[ $fileID ][ 'output_filename' ] = preg_replace( "#(\\.xlf)+#i", ".xlf", $output_content[ $fileID ][ 'output_filename' ] );
                    }
                }

                /**
                 * Conversion Enforce
                 */
                $convertBackToOriginal = true;

                //if it is a not converted file ( sdlxliff ) we have originalFile equals to xliffFile (it has just been copied)
                if ( AbstractFilesStorage::isOnS3() ) {
                    $originalFilePath = $file[ 'originalFilePath' ];

                    $file[ 'original_file' ] = $s3Client->openItem( [
                            'bucket' => S3FilesStorage::getFilesStorageBucket(),
                            'key'    => $originalFilePath
                    ] );
                } else {
                    $file[ 'original_file' ] = file_get_contents( $file[ 'originalFilePath' ] );
                }

                // When the 'proprietary' flag is set to false, the xliff
                // is not passed to any converter, because is handled
                // directly inside MateCAT.
                $xliffWasNotConverted = ( $fileType[ 'proprietary' ] === false );

                if ( empty( INIT::$FILTERS_ADDRESS ) || ( $file[ 'originalFilePath' ] == $file[ 'xliffFilePath' ] and $xliffWasNotConverted ) or $this->forceXliff ) {
                    $convertBackToOriginal = false;
                    Log::doJsonLog( "SDLXLIFF: {$file['filename']} --- " . var_export( $convertBackToOriginal, true ) );
                } else {
                    //TODO: dos2unix ??? why??
                    //force unix type files
                    Log::doJsonLog( "NO SDLXLIFF, Conversion enforced: {$file['filename']} --- " . var_export( $convertBackToOriginal, true ) );
                }

                if ( $convertBackToOriginal ) {

                    $output_content[ $fileID ][ 'out_xliff_name' ] = $outputPath;
                    $output_content[ $fileID ][ 'source' ]         = $jobData[ 'source' ];
                    $output_content[ $fileID ][ 'target' ]         = $jobData[ 'target' ];

                    $files_to_be_converted [ $fileID ] = $output_content[ $fileID ];

                }

            }

            $convertResult = Filters::xliffToTarget( $files_to_be_converted );

            // check for errors and log them on fatal_errors.txt
            foreach ( $convertResult as $id => $result ){
                if($result['isSuccess'] === false and isset($result['errorMessage'])){
                    Log::$fileName = 'fatal_errors.txt';
                    Log::doJsonLog( "FILE CONVERSION ERROR: " . $result['errorMessage'] );
                }
            }

            foreach ( array_keys( $files_to_be_converted ) as $pos => $fileID ) {

                Filters::logConversionToTarget( $convertResult[ $fileID ], $files_to_be_converted[ $fileID ][ 'out_xliff_name' ], $jobData, $chunk[ $pos ] );

                $output_content[ $fileID ][ 'document_content' ] = $this->ifGlobalSightXliffRemoveTargetMarks( $convertResult[ $fileID ] [ 'document_content' ], $files_to_be_converted[ $fileID ][ 'output_filename' ] );

                /**
                 * Because of a bug in the filters for the cjk languages ( Exception when downloading translations )
                 * we add an hook to allow some plugins to force the conversion parameters ( languages for example )
                 *
                 * We restore the right language here
                 *
                 * TODO: ( 25/05/2018 ) Remove when the issue will be fixed
                 */
                $output_content[ $fileID ][ 'document_content' ] = $this->featureSet->filter( 'overrideConversionResult',
                        $output_content[ $fileID ][ 'document_content' ],
                        Langs_Languages::getInstance()->getLangRegionCode( $jobData[ 'target' ] )
                );


                //in case of .strings, they are required to be in UTF-16
                //get extension to perform file detection
                $extension = AbstractFilesStorage::pathinfo_fix( $output_content[ $fileID ][ 'output_filename' ], PATHINFO_EXTENSION );
                if ( strtoupper( $extension ) == 'STRINGS' ) {
                    //use this function to convert stuff
                    $encodingConvertedFile = CatUtils::convertEncoding( 'UTF-16', $output_content[ $fileID ][ 'document_content' ] );


                    //strip previously added BOM
                    $encodingConvertedFile[ 1 ] = Utils::stripFileBOM( $encodingConvertedFile[ 1 ], 16 );

                    //store new content
                    $output_content[ $fileID ][ 'document_content' ] = $encodingConvertedFile[ 1 ];

                    //trash temporary data
                    unset( $encodingConvertedFile );
                }


            }

            unset( $convertResult );

        }

        foreach ( $output_content as $idFile => $fileInformations ) {
            $zipPathInfo = ZipArchiveExtended::zipPathInfo( $output_content[ $idFile ][ 'output_filename' ] );
            if ( is_array( $zipPathInfo ) ) {
                $output_content[ $idFile ][ 'zipfilename' ]     = $zipPathInfo[ 'zipfilename' ];
                $output_content[ $idFile ][ 'zipinternalPath' ] = $zipPathInfo[ 'dirname' ];
                $output_content[ $idFile ][ 'output_filename' ] = $zipPathInfo[ 'basename' ];
            }
        }

        if ( $this->download_type == 'omegat' ) {

            $this->sessionStart();
            $this->setUserCredentials();
            $OTdownloadDecorator = new DownloadOmegaTDecorator( $this );
            $output_content      = array_merge( $output_content, $OTdownloadDecorator->decorate() );
            $OTdownloadDecorator->createOmegaTZip( $output_content );
            $this->disableSessions();

        } else {

            try {

                $pathinfo = AbstractFilesStorage::pathinfo_fix( $this->getDefaultFileName( $this->project ) );

                if ( $this->anyRemoteFile() && !$this->forceXliff ) {

                    $filename = $this->generateFilename($this->getDefaultFileName( $this->project ), $jobData[ 'target' ]);

                    $this->setFilename( $filename );
                    $this->startRemoteFileService( $output_content );

                    if ( $this->openOriginalFiles ) {
                        $this->outputResultForOriginalFiles();
                    } else {
                        $this->updateRemoteFiles( $this->getOutputContentsWithZipFiles( $output_content ) );
                        $this->outputResultForRemoteFiles();
                    }

                } else {
                    $output_content = $this->getOutputContentsWithZipFiles( $output_content );

                    $this->featureSet->run( 'processZIPDownloadPreview', $this, $output_content );

                    if ( count( $output_content ) > 1 ) {

                        // cast $output_content elements to ZipContentObject
                        foreach ( $output_content as $key => $__output_content_elem ) {
                            $output_content[ $key ] = new ZipContentObject( $__output_content_elem );
                        }

                        if ( $this->forceXliff ) {
                            $_fName = $this->id_job;
                        } else {
                            $_fName = $pathinfo[ 'basename' ];
                        }

                        $nFinfo = AbstractFilesStorage::pathinfo_fix( $_fName );
                        $this->setFilename( ($nFinfo['extension'] === 'zip') ? $_fName : $_fName . ".zip" );
                        $this->outputContent = self::composeZip( $output_content ); //add zip archive content here;
                        $this->setMimeType();

                    } else {

                        // always an array with 1 element, pop it, Ex: array( array() )
                        $oContent = array_pop( $output_content );

                        $filename = $this->generateFilename($oContent->output_filename);

                        if ( $pathinfo[ 'extension' ] == 'zip' ) {
                            $this->setFilename( $filename );
                        } else {
                            $this->setFilename( self::forceOcrExtension( $filename . ( $this->forceXliff ? ".xlf" : null ) ) );
                        }

                        $this->setOutputContent( $oContent );
                        $this->setMimeType();
                    }
                }

            } catch ( Exception $e ) {

                $msg           = "\n\n Error retrieving file content, Conversion failed??? \n\n Error: {$e->getMessage()} \n\n" . var_export( $e->getTraceAsString(), true );
                $msg           .= "\n\n Request: " . var_export( $_REQUEST, true );
                Log::$fileName = 'fatal_errors.txt';
                Log::doJsonLog( $msg );
                Utils::sendErrMailReport( $msg );
                $this->unlockToken(
                        [
                                "code"    => -110,
                                "message" => "Download failed. Please, try again in 5 minutes. If it still fails, please, contact " . INIT::$SUPPORT_MAIL
                        ]
                );

                throw $e; // avoid sent Headers and empty file content with finalize method
            }

        }

        try {
            Utils::deleteDir( INIT::$TMP_DOWNLOAD . '/' . $this->id_job . '/' );
        } catch ( Exception $e ) {
            Log::doJsonLog( 'Failed to delete dir:' . $e->getMessage() );
        }

        $this->_saveActivity();

    }

    /**
     * @param string $originalFilename
     * @param null $target
     *
     * @return mixed|string
     */
    private function generateFilename($originalFilename, $target = null) {
        $pathInfo = AbstractFilesStorage::pathinfo_fix( $originalFilename );
        $extension = ($this->isAnIWorkFile($pathInfo[ 'extension' ])) ? $this->overrideExtensionForIWorkFiles($pathInfo[ 'extension' ])  : $pathInfo[ 'extension' ];

        $filename = '';

        if(isset($pathInfo['dirname']) and $pathInfo['dirname'] != ''){
            $filename .= $pathInfo['dirname'] . DIRECTORY_SEPARATOR;
        }

        $filename .= $pathInfo[ 'filename' ];

        if($target){
            $filename .= "_" . $target;
        }

        $filename .= "." .$extension;

        return $filename;
    }

    /**
     * @param string $extension
     *
     * @return bool
     */
    private function isAnIWorkFile($extension) {
        return in_array($extension, ['pages', 'numbers', 'key']);
    }

    /**
     * We need to convert iWorks file extensions
     * because Matecat filters converts them
     * to the corresponding MS Office format
     *
     * @param string $extension
     *
     * @return string
     */
    private function overrideExtensionForIWorkFiles($extension){

        switch ($extension){
            case "key":
                return "pptx";

            case "numbers":
                return "xlsx";

            case "pages":
                return "docx";
        }
    }

    /**
     * @param array $convertResult
     * @param int   $fileID
     *
     * @return bool
     */
    private function wasTheFileSuccessfullyConverted( $convertResult, $fileID ) {
        $isSuccess = $convertResult[ $fileID ][ 'isSuccess' ];
        $content   = $convertResult[ $fileID ] [ 'document_content' ];

        return ( true === $isSuccess and null !== $content );
    }

    protected function _saveActivity() {

        $redisHandler = new RedisHandler();
        $job_complete = $redisHandler->getConnection()->get( 'job_completeness:' . $this->id_job );

        if ( $this->download_type == 'omegat' ) {
            $action = ActivityLogStruct::DOWNLOAD_OMEGAT;
        } elseif ( $this->forceXliff ) {
            $action = ActivityLogStruct::DOWNLOAD_XLIFF;
        } elseif ( $this->anyRemoteFile() ) {
            $action = ( $job_complete ? ActivityLogStruct::DOWNLOAD_GDRIVE_TRANSLATION : ActivityLogStruct::DOWNLOAD_GDRIVE_PREVIEW );
        } else {
            $action = ( $job_complete ? ActivityLogStruct::DOWNLOAD_TRANSLATION : ActivityLogStruct::DOWNLOAD_PREVIEW );
        }

        /**
         * Retrieve user information
         */
        $this->readLoginInfo();

        $activity             = new ActivityLogStruct();
        $activity->id_job     = $this->id_job;
        $activity->id_project = $this->job[ 'id_project' ];
        $activity->action     = $action;
        $activity->ip         = Utils::getRealIpAddr();
        $activity->uid        = $this->user->uid;
        $activity->event_date = date( 'Y-m-d H:i:s' );
        Activity::save( $activity );

    }

    /**
     * Initializes remoteFiles property reading entries from database
     *
     * Cached result to avoid query executed more than once
     *
     * @return bool
     */
    private function anyRemoteFile() {
        if ( is_null( $this->trereIsARemoteFile ) ) {
            $this->trereIsARemoteFile = \RemoteFiles_RemoteFileDao::jobHasRemoteFiles( $this->id_job );
        }

        return $this->trereIsARemoteFile;
    }

    /**
     * @throws Exception
     */
    private function outputResultForOriginalFiles() {
        $files = \RemoteFiles_RemoteFileDao::getOriginalsByJobId( $this->id_job );

        $response = [ 'urls' => [] ];

        foreach ( $files as $file ) {
            $gdriveFile = $this->remoteFileService->getFileLink( $file->remote_id );

            if ( empty( $gdriveFile[ 'webViewLink' ] ) ) {
                throw new Exception( 'alternateLink was not found for file with local ID ' . $file->id );
            }

            $response[ 'urls' ][] = [
                    'localId'       => $file->id,
                    'alternateLink' => $gdriveFile->getWebViewLink()
            ];
        }

        echo json_encode( $response );
    }

    /**
     * @throws Exception
     */
    private function outputResultForRemoteFiles() {
        $response = [ 'urls' => [] ];

        foreach ( $this->remoteFiles as $localId => $file ) {
            $response[ 'urls' ][] = [
                    'localId'       => $localId,
                    'alternateLink' => $file[ 'webViewLink' ]
            ];
        }

        echo json_encode( $response );
    }

    /**
     * This prepares the object that will handle communication with remote file service.
     * We assume that the whole project was created with files coming from the same remote account.
     * We look for the first remote_file record and seek for the connected service to read for the auth_token.
     *
     * @param $output_content
     *
     * @throws Exception
     */
    private function startRemoteFileService( $output_content ) {
        $keys        = array_keys( $output_content );
        $firstFileId = $keys[ 0 ];

        // find the proper remote file by id_job and file_id
        $remoteFile = RemoteFiles_RemoteFileDao::getByFileAndJob( $firstFileId, $this->job->id );

        $dao              = new ConnectedServiceDao();
        $connectedService = $dao->findById( $remoteFile->connected_service_id );

        if ( !$connectedService || $connectedService->disabled_at ) {
            // TODO: check how this exception is handled
            throw new Exception( 'Connected service missing or disabled' );
        }

        $verifier  = new GDriveTokenVerifyModel( $connectedService );
        $raw_token = $connectedService->getDecryptedOauthAccessToken();

        if ( $verifier->validOrRefreshed() ) {
            $this->remoteFileService = new GDrive\RemoteFileService( $raw_token );
        } else {
            // TODO: check how this exception is handled
            throw new Exception( 'Unable to refresh token for service' );
        }
    }

    /**
     * @param ZipContentObject[] $output_content
     *
     * @throws Exception
     */
    private function updateRemoteFiles( $output_content ) {
        foreach ( $output_content as $id_file => $output_file ) {
            $remoteFile                           = \RemoteFiles_RemoteFileDao::getByFileAndJob( $id_file, $this->job->id );
            $this->remoteFiles[ $remoteFile->id ] = $this->remoteFileService->updateFile( $remoteFile, $output_file->getContent() );
        }
    }

    /**
     * Remove the tag mrk if the file is an xlif and if the file is a globalsight file
     *
     * Also, check for encoding and transform utf16 to utf8 and back
     *
     * @param $documentContent
     * @param $path
     *
     * @return string
     */
    public function ifGlobalSightXliffRemoveTargetMarks( $documentContent, $path ) {

        if ( !XliffFiles::isXliff( $path ) ) {
            return $documentContent;
        }

        $is_utf8          = true;
        $original_charset = 'utf-8'; //not used, useful only to avoid IDE warning for not used variable

        //The file is UTF-16 Encoded
        if ( stripos( substr( $documentContent, 0, 100 ), "<?xml " ) === false ) {

            $is_utf8 = false;
            list( $original_charset, $documentContent ) = CatUtils::convertEncoding( 'UTF-8', $documentContent );

        }

        //avoid in memory copy of very large files if possible
        $detect_result = XliffProprietaryDetect::getInfoByStringData( substr( $documentContent, 0, 1024 ) );

        //clean mrk tags for GlobalSight application compatibility
        //this should be a sax parser instead of in memory copy for every trans-unit
        if ( $detect_result[ 'proprietary_short_name' ] == 'globalsight' ) {

            // Getting Trans-units
            $trans_units = explode( '<trans-unit', $documentContent );

            foreach ( $trans_units as $pos => $trans_unit ) {

                // First element in the XLIFF split is the header, not the first file
                if ( $pos > 0 ) {

                    //remove seg-source tags
                    $trans_unit = preg_replace( '|<seg-source.*?</seg-source>|si', '', $trans_unit );
                    //take the target content
                    $trans_unit = preg_replace( '#<mrk[^>]+>|</mrk>#si', '', $trans_unit );

                    $trans_units[ $pos ] = $trans_unit;

                }

            } // End of trans-units

            $documentContent = implode( '<trans-unit', $trans_units );

        }

        if ( !$is_utf8 ) {
            list( $__utf8, $documentContent ) = CatUtils::convertEncoding( $original_charset, $documentContent );
        }

        return $documentContent;

    }

    private function getOutputContentsWithZipFiles( $output_content ) {

        $zipFiles         = [];
        $newOutputContent = [];

        //group files by zip archive
        foreach ( $output_content as $idFile => $fileInformations ) {

            $fileInformations['output_filename'] = $this->generateFilename($fileInformations['output_filename']);
            $output_content[ $idFile ]['output_filename'] = $fileInformations['output_filename'];

            // If this file comes from a ZIP, add it to $zipFiles
            if ( isset( $fileInformations[ 'zipfilename' ] ) ) {
                $zipFileName = $fileInformations[ 'zipfilename' ];

                $zipFiles[ $zipFileName ][] = $fileInformations;
                unset( $output_content[ $idFile ] );

            }

        }

        unset( $idFile );
        unset( $fileInformations );

        //for each zip file index, compose zip again, save it to a temporary location and add it into output_content
        foreach ( $zipFiles as $zipFileName => $internalFile ) {

            foreach ( $internalFile as $__idx => $fileInformations ) {

                if ( $fileInformations[ 'zipinternalPath' ] != "" ) {
                    $internalDirName = $fileInformations[ 'zipinternalPath' ] . DIRECTORY_SEPARATOR;
                } else {
                    $internalDirName = null;
                }

                $zipFiles[ $zipFileName ][ $__idx ][ 'output_filename' ] = $internalDirName . $fileInformations[ 'output_filename' ];

                unset( $zipFiles[ $zipFileName ][ $__idx ][ 'zipinternalPath' ] );
                unset( $zipFiles[ $zipFileName ][ $__idx ][ 'zipfilename' ] );
                unset( $zipFiles[ $zipFileName ][ $__idx ][ 'source' ] );
                unset( $zipFiles[ $zipFileName ][ $__idx ][ 'target' ] );
                unset( $zipFiles[ $zipFileName ][ $__idx ][ 'out_xliff_name' ] );
            }

            $internalFile = $zipFiles[ $zipFileName ];
            $internalFile = $this->getOutputContentsWithZipFiles( $internalFile );

            foreach ( $internalFile as $key => $iFile ) {
                $internalFile[ $key ] = new ZipContentObject( $iFile );
            }

            $zip = $this->reBuildZipContent( $zipFileName, $internalFile );

            $newOutputContent[] = new ZipContentObject( [
                    'output_filename'  => $zipFileName,
                    'document_content' => null,
                    'input_filename'   => $zip,
            ] );
        }

        foreach ( $output_content as $idFile => $content ) {

            //this is true only for files that are not inside a zip ( normal uploaded files )
            if ( isset( $output_content[ $idFile ][ 'out_xliff_name' ] ) ) {
                //rename the key to make this compatible with ZipContentObject
                $output_content[ $idFile ][ 'input_filename' ] = $output_content[ $idFile ][ 'out_xliff_name' ];
                //remove the other invalid keys
                unset( $output_content[ $idFile ][ 'out_xliff_name' ] );
                unset( $output_content[ $idFile ][ 'source' ] );
                unset( $output_content[ $idFile ][ 'target' ] );

            }

            $output_content[ $idFile ] = new ZipContentObject( $output_content[ $idFile ] );

        }

        $newOutputContent = $newOutputContent + $output_content;

        return $newOutputContent;
    }

    /**
     * @param $zipFileName
     * @param $newInternalZipFiles ZipContentObject[]
     *
     * @return string
     * @throws Exception
     */
    public function reBuildZipContent( $zipFileName, $newInternalZipFiles ) {

        $project = Projects_ProjectDao::findById( $this->job[ 'id_project' ] );

        // this is the filesystem path
        $zipFile  = ( new FilesStorage\FsFilesStorage() )->getOriginalZipPath( $project->create_date, $this->job[ 'id_project' ], $zipFileName );
        $tmpFName = tempnam( INIT::$TMP_DOWNLOAD . '/' . $this->id_job . '/', "ZIP" );

        $isFsOnS3 = AbstractFilesStorage::isOnS3();
        if ( $isFsOnS3 and false === file_exists( $zipFile ) ) {
            // transfer zip file to tmp path
            $fs      = FilesStorageFactory::create();
            $zipPath = $fs->getOriginalZipPath( $project->create_date, $this->job[ 'id_project' ], $zipFileName );
            $this->transferZipFromS3ToTmpDir( $zipPath, $tmpFName );
        } else {
            copy( $zipFile, $tmpFName );
        }

        $zip = new ZipArchiveExtended();
        if ( $zip->open( $tmpFName ) ) {

            $zip->createTree();

            //rebuild the real name of files in the zip archive
            foreach ( $zip->treeList as $filePath ) {

                $realZipFilePath = str_replace(
                        [
                                ZipArchiveExtended::INTERNAL_SEPARATOR,
                                AbstractFilesStorage::pathinfo_fix( $tmpFName, PATHINFO_BASENAME )
                        ],
                        [ DIRECTORY_SEPARATOR, "" ],
                        $filePath );
                $realZipFilePath = ltrim( $realZipFilePath, "/" );
                $newRealZipFilePath = $this->generateFilename($realZipFilePath);

                //remove the tmx from the original zip ( we want not to be exported as preview )
                if ( AbstractFilesStorage::pathinfo_fix( $newRealZipFilePath, PATHINFO_EXTENSION ) == 'tmx' ) {
                    $zip->deleteName( $newRealZipFilePath );
                    $zip->deleteName( $realZipFilePath );
                    continue;
                }

                // fix the file names inside the zip file, so we compare with our files
                // and if matches we can substitute them with the converted ones
                foreach ( $newInternalZipFiles as $index => $newInternalZipFile ) {

                    if ( $this->forceXliff ) {

                        //
                        // ---------------------------------------------
                        // NOTE 2021-06-11
                        // ---------------------------------------------
                        //
                        // If we are downloading intermediate xlf files at this point we have files in this format:
                        //
                        // xxxx.xlf
                        //
                        // If the zip contains xliff files we have for example:
                        //
                        // test.sdlxliff.xlf
                        //
                        // And we can't use the regex /\.xlf|\.xliff|\.sdlxliff$/, because in this case we obtain:
                        //
                        // test
                        //
                        // (the regex trimmed out .sdlxliff.xlf)
                        //
                        // Much better using AbstractFilesStorage::pathinfo_fix function to get the real filename (with no xlf extension)
                        //
                        $declaredOutputFileName = AbstractFilesStorage::pathinfo_fix( $newInternalZipFile->output_filename, PATHINFO_FILENAME );
                        $isTheSameFile          = ( $declaredOutputFileName == $newRealZipFilePath );
                    } else {
                        $isTheSameFile = ( $newInternalZipFile->output_filename == $newRealZipFilePath );
                    }

                    if ( $isTheSameFile ) {

                        $zip->deleteName( $realZipFilePath );
                        $zip->deleteName( $newRealZipFilePath );

                        if ( AbstractFilesStorage::pathinfo_fix( $newRealZipFilePath, PATHINFO_EXTENSION ) == 'pdf' ) {
                            $newRealZipFilePath .= '.docx';
                        } elseif ( $this->forceXliff ) {
                            $newRealZipFilePath = $newInternalZipFile->output_filename;
                        }

                        $zip->addFromString( $newRealZipFilePath, $newInternalZipFile->getContent() );

                    }
                }

            }

            $zip->close();

        }

        return $tmpFName;

    }

    /**
     * @param $zipPath
     * @param $tmpDir
     *
     * @throws ReflectionException
     * @throws \Predis\Connection\ConnectionException
     */
    public function transferZipFromS3ToTmpDir( $zipPath, $tmpDir ) {

        Log::doJsonLog( "Downloading original zip " . $zipPath . " from S3 to tmp dir " . $tmpDir );

        /** @var $s3Client Client */
        $s3Client            = S3FilesStorage::getStaticS3Client();
        $params[ 'bucket' ]  = \INIT::$AWS_STORAGE_BASE_BUCKET;
        $params[ 'key' ]     = $zipPath;
        $params[ 'save_as' ] = $tmpDir;
        $s3Client->downloadItem( $params );
    }
}
