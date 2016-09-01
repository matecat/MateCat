<?php
/**
 * Created by PhpStorm.
 * User: Hashashiyyin
 * Date: 29/08/16
 * Time: 17:12
 */

namespace API\V2;

use DateTime;
use ReflectionClass;
use TMSService, Upload, Exception, Log;
use PHPExcel_IOFactory;
use PHPExcel_Writer_CSV;
use Utils;

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

        $this->name   = $postInput->name;
        $this->tm_key = $postInput->tm_key;

        $this->TMService->setName( $postInput->name );
        $this->TMService->setTmKey( $postInput->tm_key );

    }

    public function import() {

        $uploadFile = new Upload();
        try {
            $stdResult = $uploadFile->uploadFiles( $_FILES );
        } catch ( Exception $e ) {
            $this->setErrorResponse( -2, $e->getMessage() );

            return;
        }

        $this->extractCSV( $stdResult );

        //load it into MyMemory
        $this->TMService->setName( $stdResult->glossary_file->name );
        $this->TMService->setFile( array( $stdResult->glossary_file ) );

        try {
            $this->TMService->addGlossaryInMyMemory();
        } catch ( Exception $e ) {
            $this->setErrorResponse( $e->getCode(), $e->getMessage() );
            unlink( $stdResult->glossary_file->file_path );

            return;
        }

        if ( !$this->response->isLocked() ) {
            $this->response->json( $this->request->paramsPost()->all() );
        }

        unlink( $stdResult->glossary_file->file_path );

    }

    //TODO serve l'endpoint corretto di MyMemory
    public function status() {

        throw new Exception( "TODO: incomplete, MyMemory lacks the endpoint." );

        try {
            $result = $this->TMService->tmxUploadStatus();
        } catch ( Exception $e ) {
            $this->setErrorResponse( $e->getCode(), $e->getMessage() );

            return;
        }

        if ( !$this->response->isLocked() ) {
            $this->response->json( $result );
        }

    }

    //TODO serve l'endpoint corretto di MyMemory
    public function download() {

        throw new Exception( "TODO: incomplete, MyMemory lacks the endpoint." );

        //cast response to KleinFileStreamResponse to override file method
        $responseOverride = new KleinFileStreamResponse(
                $this->response->body(),
                $this->response->code()
        );

        $reflectionCass   = new ReflectionClass( $this->response );
        $cookiesProperty   = $reflectionCass->getProperty( 'cookies' );
        $cookiesProperty->setAccessible( true );
        $cookiesVal = $cookiesProperty->getValue( 'cookies' );
        $responseOverride->cookie( $cookiesVal );

        $headersProperty   = $reflectionCass->getProperty( 'headers' );
        $headersProperty->setAccessible( true );
        $headersVal = $headersProperty->getValue( 'headers' );
        $responseOverride->headers( $headersVal );

        $this->response = $responseOverride;

        try {

            //TODO change with something
            $filePointer = $this->TMService->downloadTMX();

            // TODO: Not used at moment, will be enabled when will be built the Log Activity Keys
            /*
                $activity             = new ActivityLogStruct();
                $activity->id_job     = $this->id_job;
                $activity->action     = ActivityLogStruct::DOWNLOAD_KEY_TMX;
                $activity->ip         = Utils::getRealIpAddr();
                $activity->uid        = $this->uid;
                $activity->event_date = date( 'Y-m-d H:i:s' );
                Activity::save( $activity );
            */

        } catch( Exception $e ){

            $r = "<pre>";
            $r .= print_r( $e->getMessage(), true );
            $r .= print_r( $e->getTraceAsString(), true );
            $r .= "\n\n";
            $r .=  " - API REQUEST URI: " . print_r( @$_SERVER['REQUEST_URI'], true ) . "\n";
            $r .=  " - API REQUEST Message: " . print_r( $_REQUEST, true ) . "\n";
            $r .= "\n\n\n";
            $r .= "</pre>";
            Log::$fileName = 'php_errors.txt';
            Log::doLog( $r );
            Utils::sendErrMailReport( $r, "API: Download Glossary Error: " . $e->getMessage() );
            $this->unlockToken();
            $this->setErrorResponse( $e->getCode(), $e->getMessage() );
            return;

        }

        $this->response->file( $filePointer, $this->tm_key . "_" . ( new DateTime() )->format( 'YmdHi' ) . ".zip", $this );

    }

    protected function setErrorResponse( $errCode, $message ) {

        $this->response->code( 404 );
        $this->response->json( [
                'errors' => [ "code" => $errCode, "message" => $message ],
                "data"   => [ ]
        ] );

    }

    protected function extractCSV( $stdResult ) {

        $tmpFileName = tempnam( "/tmp", "MAT_EXCEL_GLOSS_" );

        $inputFileType = PHPExcel_IOFactory::identify( $stdResult->glossary_file->file_path );
        $objReader     = PHPExcel_IOFactory::createReader( $inputFileType );

        $objPHPExcel = $objReader->load( $stdResult->glossary_file->file_path );
        $objWriter   = new PHPExcel_Writer_CSV( $objPHPExcel );
        $objWriter->save( $tmpFileName );

        $oldPath                             = $stdResult->glossary_file->file_path;
        $stdResult->glossary_file->file_path = $tmpFileName;
        Log::doLog( "Originally uploaded File path: " . $oldPath . " - Override: " . $stdResult->glossary_file->file_path );

        unlink( $oldPath );

        return $stdResult;

    }

    public function unlockToken( $tokenContent = null ) {

        if ( isset( $this->downloadToken ) && !empty( $this->downloadToken ) ) {
            setcookie(
                    $this->downloadToken,
                    ( empty( $tokenContent ) ? json_encode( array(
                            "code"    => 0,
                            "message" => "Download complete."
                    ) ) : json_encode( $tokenContent ) ),
                    2147483647            // expires January 1, 2038
            );
            $this->downloadToken = null;
        }

    }

}