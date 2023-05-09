<?php
/**
 * Created by PhpStorm.
 * User: Hashashiyyin
 * Date: 29/08/16
 * Time: 17:12
 */

namespace API\V2;

use ActivityLog\Activity;
use ActivityLog\ActivityLogStruct;
use API\App\AbstractStatefulKleinController;
use API\V2\Validators\LoginValidator;
use DirectoryIterator;
use Exception;
use Log;
use PHPExcel_IOFactory;
use PHPExcel_Writer_CSV;
use TMS\TMSFile;
use TMS\TMSService;
use Users_UserDao;
use Utils;
use ZipArchive;

class GlossariesController extends AbstractStatefulKleinController {

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
        \Bootstrap::sessionClose();
        $this->appendValidator( new LoginValidator( $this ) );
    }

    protected function validateRequest() {

        parent::validateRequest();

        $filterArgs = [
                'name'          => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW
                ],
                'tm_key'        => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'downloadToken' => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
        ];

        $postInput = (object)filter_var_array( $this->request->params(
                [
                        'tm_key',
                        'name',
                        'downloadToken'
                ]
        ), $filterArgs );

        $this->name          = $postInput->name;
        $this->tm_key        = $postInput->tm_key;
        $this->downloadToken = $postInput->downloadToken;

    }

    /**
     * @throws Exception
     */
    public function import() {

        try {
            $stdResult = $this->TMService->uploadFile();
        } catch ( Exception $e ) {
            $this->setErrorResponse( 500, $e->getMessage() );

            return;
        }

        $filterArgs = [
                'name'   => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW
                ],
                'tm_key' => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
        ];

        $postInput = (object)filter_var_array( $this->request->params( [
                'tm_key',
                'name',
        ] ), $filterArgs );

        if ( !isset( $postInput->tm_key ) or $postInput->tm_key === "" ) {
            $this->setErrorResponse( 400, "`TM key` field is mandatory" );

            return;
        }

        set_time_limit( 600 );

        $this->extractCSV( $stdResult );

        $uuids = [];

        try {

            foreach ( $stdResult as $fileInfo ) {

                // load it into MyMemory
                try {

                    $file = new TMSFile(
                            $fileInfo->file_path,
                            $postInput->tm_key,
                            $postInput->name
                    );

                    $this->TMService->addGlossaryInMyMemory( $file );

                    $uuids[] = [ "uuid" => $file->getUuid(), "name" => $file->getName() ];

                } catch ( Exception $e ) {
                    $this->setErrorResponse( $e->getCode(), $e->getMessage() );
                    return;
                }

            }

        } finally {
            foreach ( $stdResult as $_fileInfo ) {
                unlink( $_fileInfo->file_path );
            }
        }

        if ( !$this->response->isLocked() ) {
            $this->setSuccessResponse( 202, [
                    'uuids' => $uuids
            ] );
        }

    }

    public function uploadStatus() {

        $uuid = $this->params[ 'uuid' ];

        try {
            $result = $this->TMService->glossaryUploadStatus( $uuid );
        } catch ( Exception $e ) {
            $this->setErrorResponse( $e->getCode(), $e->getMessage() );

            return;
        }

        if ( !$this->response->isLocked() ) {
            $this->setSuccessResponse( $result->responseStatus, $result[ 'data' ] );
        }

    }

    public function download() {

        $filterArgs = [
                'key_name' => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW
                ],
                'key'      => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'email'    => [
                        'filter' => FILTER_SANITIZE_EMAIL,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
        ];

        $postInput = (object)filter_var_array( $this->request->params( [
                'email',
                'key_name',
                'key',
        ] ), $filterArgs );

        try {
            $user = ( new Users_UserDao() )->getByEmail( $postInput->email );

            if ( !$user ) {
                $this->setErrorResponse( 404, "User with email " . $postInput->email . " not found" );

                return;
            }

            $result = $this->TMService->glossaryExport( $postInput->key, $postInput->key_name, $postInput->email, $user->fullName() );
        } catch ( Exception $e ) {
            $this->setErrorResponse( $e->getCode(), $e->getMessage() );

            return;
        }

        if ( !$this->response->isLocked() ) {
            $this->setSuccessResponse( $result->responseStatus, $result->responseData );
        }
    }

    protected function logDownloadError( Exception $e ) {
        $r             = "<pre>";
        $r             .= print_r( $e->getMessage(), true );
        $r             .= print_r( $e->getTraceAsString(), true );
        $r             .= "\n\n";
        $r             .= " - API REQUEST URI: " . print_r( @$_SERVER[ 'REQUEST_URI' ], true ) . "\n";
        $r             .= " - API REQUEST Message: " . print_r( $_REQUEST, true ) . "\n";
        $r             .= "\n\n\n";
        $r             .= "</pre>";
        Log::$fileName = 'php_errors.txt';
        Log::doJsonLog( $r );
        Utils::sendErrMailReport( $r, "API: Download Glossary Error: " . $e->getMessage() );

    }

    /**
     * @unused
     */
    protected function recordDownloadActivity() {
        $activity             = new ActivityLogStruct();
        $activity->id_job     = $this->id_job;
        $activity->action     = ActivityLogStruct::DOWNLOAD_KEY_TMX;
        $activity->ip         = Utils::getRealIpAddr();
        $activity->uid        = $this->user->uid;
        $activity->event_date = date( 'Y-m-d H:i:s' );
        Activity::save( $activity );
    }

    protected function setErrorResponse( $errCode, $message ) {

        $this->response->code( 404 );
        $this->response->json( [
                'errors'    => [ [ "code" => $errCode, "message" => $message ] ],
                "data"      => [],
                "success"   => false,
                "completed" => false
        ] );

    }

    protected function setSuccessResponse( $code = 200, array $data = [] ) {

        $this->response->code( $code );
        $this->response->json( [
                'errors'    => [],
                "data"      => $data,
                "success"   => true
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
            Log::doJsonLog( "Originally uploaded File path: " . $oldPath . " - Override: " . $fileInfo->file_path );

            unlink( $oldPath );

        }

        return $stdResult;

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