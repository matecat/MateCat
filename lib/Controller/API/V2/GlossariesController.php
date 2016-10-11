<?php
/**
 * Created by PhpStorm.
 * User: Hashashiyyin
 * Date: 29/08/16
 * Time: 17:12
 */

namespace API\V2;

use ActivityLog\Activity;
use DateTime;
use DirectoryIterator;
use TMSService, Exception, Log;
use PHPExcel_IOFactory;
use PHPExcel_Writer_CSV;
use Utils;
use ActivityLog\ActivityLogStruct;
use ZipArchive;

class GlossariesController extends KleinController {

    /**
     * @var \Klein\Request
     */
    protected $request;

    protected $name;
    protected $tm_key;

    /**
     * @var TMSService
     */
    protected $TMService;

    /**
     * @var string
     */
    public $downloadToken;

    protected function afterConstruct() {
        $this->TMService = new TMSService();
        $this->validateRequest();
    }

    protected function validateRequest() {

        $filterArgs = array(
                'name'          => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW
                ),
                'tm_key'        => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'downloadToken' => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
        );

        $postInput = (object)filter_var_array( $this->request->params(
                array(
                        'tm_key',
                        'name',
                        'downloadToken'
                )
        ), $filterArgs );

        $this->name          = $postInput->name;
        $this->tm_key        = $postInput->tm_key;
        $this->downloadToken = $postInput->downloadToken;

        $this->TMService->setName( $postInput->name );
        $this->TMService->setTmKey( $postInput->tm_key );

    }

    public function import() {

        try {
            $stdResult = $this->TMService->uploadFile();
        } catch ( Exception $e ) {
            $this->setErrorResponse( -2, $e->getMessage() );

            return;
        }

        $fileInfo = $this->extractCSV( $stdResult );

        //load it into MyMemory
        $this->TMService->setName( $fileInfo->name );
        $this->TMService->setFile( array( $fileInfo ) );

        try {
            $this->TMService->addGlossaryInMyMemory();
        } catch ( Exception $e ) {
            $this->setErrorResponse( $e->getCode(), $e->getMessage() );
            unlink( $fileInfo->file_path );

            return;
        }

        if ( !$this->response->isLocked() ) {
            $this->setSuccessResponse( 202 );
        }

        unlink( $fileInfo->file_path );

    }

    public function uploadStatus() {

        try {
            $result = $this->TMService->tmxUploadStatus();
        } catch ( Exception $e ) {
            $this->setErrorResponse( $e->getCode(), $e->getMessage() );

            return;
        }

        if ( !$this->response->isLocked() ) {
            $this->setSuccessResponse( null, $result[ 'data' ] );
        }

    }

    public function download() {

        try {

            $ZipFilePointer = $this->rebuildExcel(
                    $this->TMService->downloadGlossary()
            );

            // TODO: Not used at moment, will be enabled when will be built the Log Activity Keys
            // $this->recordActivity();

        } catch ( Exception $e ) {
            $this->logDownloadError( $e );
            $this->unlockDownloadToken();
            $this->setErrorResponse( $e->getCode(), $e->getMessage() );

            return;
        }

        $this->unlockDownloadToken();

        $fileStream = new KleinResponseFileStream( $this->response );
        $fileName   = $this->tm_key . "_" . ( new DateTime() )->format( 'YmdHi' ) . ".zip";
        $fileStream->streamFileFromPointer( $ZipFilePointer, $fileName );
    }

    protected function logDownloadError( Exception $e ) {
        $r = "<pre>";
        $r .= print_r( $e->getMessage(), true );
        $r .= print_r( $e->getTraceAsString(), true );
        $r .= "\n\n";
        $r .= " - API REQUEST URI: " . print_r( @$_SERVER[ 'REQUEST_URI' ], true ) . "\n";
        $r .= " - API REQUEST Message: " . print_r( $_REQUEST, true ) . "\n";
        $r .= "\n\n\n";
        $r .= "</pre>";
        Log::$fileName = 'php_errors.txt';
        Log::doLog( $r );
        Utils::sendErrMailReport( $r, "API: Download Glossary Error: " . $e->getMessage() );

    }

    protected function recordDownloadActivity() {
        $activity             = new ActivityLogStruct();
        $activity->id_job     = $this->id_job;
        $activity->action     = ActivityLogStruct::DOWNLOAD_KEY_TMX;
        $activity->ip         = Utils::getRealIpAddr();
        $activity->uid        = $this->uid;
        $activity->event_date = date( 'Y-m-d H:i:s' );
        Activity::save( $activity );
    }

    protected function setErrorResponse( $errCode, $message ) {

        $this->response->code( 404 );
        $this->response->json( [
                'errors'  => [ [ "code" => $errCode, "message" => $message ] ],
                "data"    => [],
                "success" => false
        ] );

    }

    protected function setSuccessResponse( $code = 200, Array $data = [] ) {

        $this->response->code( $code );
        $this->response->json( [
                'errors'  => [],
                "data"    => $data,
                "success" => true
        ] );

    }

    protected function extractCSV( $stdResult ) {

        $tmpFileName = tempnam( "/tmp", "MAT_EXCEL_GLOSS_" );

        //$stdResult in this case handle everytime only a file, this cycle needs to make this method
        // not dipendent on the form key name
        foreach ( $stdResult as $fileInfo ) {

            $inputFileType = PHPExcel_IOFactory::identify( $fileInfo->file_path );
            $objReader     = PHPExcel_IOFactory::createReader( $inputFileType );

            $objPHPExcel = $objReader->load( $fileInfo->file_path );
            $objWriter   = new PHPExcel_Writer_CSV( $objPHPExcel );
            $objWriter->save( $tmpFileName );

            $oldPath             = $fileInfo->file_path;
            $fileInfo->file_path = $tmpFileName;
            Log::doLog( "Originally uploaded File path: " . $oldPath . " - Override: " . $fileInfo->file_path );

            unlink( $oldPath );

        }

        return $fileInfo;

    }

    /**
     * @param $ZipFileName String
     *
     * @return resource
     *
     * @throws Exception
     */
    protected function rebuildExcel( $ZipFileName ) {


        $zipFile = new ZipArchive;
        if ( $zipFile->open( $ZipFileName ) === true ) {

            $inDirName  = "/tmp/gls_" . uniqid( "", true );
            $outDirName = "$inDirName/gls_" . uniqid( "", true );
            mkdir( $inDirName );
            mkdir( $outDirName );
            $zipFile->extractTo( $inDirName );
            $zipFile->close();

            $dirIterator = new DirectoryIterator ( $inDirName );

            //create file after directory scan to exclude the zip file in the file list
            $zipFile->open( $inDirName . "zip.zip", ZipArchive::CREATE );

            /**
             * @var $fileInfo \SplFileObject
             */
            foreach ( $dirIterator as $fileInfo ) {

                if ( $fileInfo->isDir() ) {
                    continue;
                }

                $fileType  = PHPExcel_IOFactory::identify( $fileInfo->getPathname() );
                $objReader = PHPExcel_IOFactory::createReader( $fileType );

                $objReader->setReadDataOnly( true );
                $objPHPExcel = $objReader->load( $fileInfo->getPathname() );

                $objWriter = PHPExcel_IOFactory::createWriter( $objPHPExcel, 'Excel2007' );

                $fileName = $outDirName . DIRECTORY_SEPARATOR . $fileInfo->getBasename( '.csv' ) . ".xlsx";
                $objWriter->save( $fileName );

                $zipFile->addFile( $fileName, $fileInfo->getBasename( '.csv' ) . ".xlsx" );

            }

            $zipFile->close();

            return fopen( $inDirName . "zip.zip", "r" );

        } else {

            //This is an hack because MyMemory does not send HTTP 404 code for the request
            // the response is stored inside the $ZipFileName
            // in this way, without CAT the file, i can not distinguish if download is failed or if the glossary is empty

            //{"responseData":{"translatedText":"NO GLOSSARY ENTRIES FOUND FOR THE GIVEN API KEY"},"responseDetails":"NO GLOSSARY ENTRIES FOUND FOR THE GIVEN API KEY","responseStatus":404,"matches":""}
            throw new Exception( "No glossary entries found for the given api key," );

        }

    }


}