<?php 

namespace Webhooks\GDrive  ;

use Bootstrap ; 
use Log;
use API\V2\KleinController ;
use Google_Http_Request ;
use Utils ;
use INIT ; 
use ConversionHandler ; 
use GDrive;

class OpenController extends KleinController {

    private $source_lang = 'en-US';
    private $target_lang = 'fr-FR';
    private $seg_rule = null;

    private $gdriveService = null;

    private $guid = null;

    public function open() {

        $this->doAuth();

        $this->correctSourceTargetLang();

        $this->doImport();

        $this->doRedirect();

    }

    private function doAuth() {
        Bootstrap::sessionStart(); 

        $this->gdriveService = GDrive::getService( $_SESSION );
    }

    private function doImport() {

        $state = json_decode( $this->request->param('state'), TRUE );

        \Log::doLog( $state );

        $this->guid = Utils::create_guid();
        setcookie( "upload_session", $this->guid, time() + 86400, '/' );

        $listOfIds = array();

        if ( array_key_exists( 'ids', $state) ) {
            $listOfIds = $state['ids'];
        }
        else if ( array_key_exists('exportIds', $state) ) {
            $listOfIds = $state['exportIds'];
        }
        else {
            throw new Exception( " no ids or export ids found ");
        }

        $countIds = count( $listOfIds );

        for( $i = 0; $i < $countIds; $i++ ) {
            $this->importFile( $listOfIds[$i] );
        }
    }

    private function importFile( $fileId ) {
        $file = $this->gdriveService->files->get( $fileId );
        $mime = GDrive::officeMimeFromGoogle( $file->mimeType );
        $links = $file->getExportLinks() ;

        $downloadUrl = '';

        if($links != null) {
            $downloadUrl = $links[ $mime ];
        } else {
            $downloadUrl = $file->getDownloadUrl();
        }

        if ($downloadUrl) {

            $fileName = $file->getTitle();
            $file_extension = GDrive::officeExtensionFromMime( $file->mimeType );

            if ( substr( $fileName, -5 ) !== $file_extension ) {
                $fileName .= $file_extension;
            }

            $request = new \Google_Http_Request( $downloadUrl, 'GET', null, null );
            $httpRequest = $this->gdriveService
                    ->getClient()
                    ->getAuth()
                    ->authenticatedRequest( $request );

            if ( $httpRequest->getResponseHttpCode() == 200 ) {
                $body = $httpRequest->getResponseBody();
                $directory = Utils::uploadDirFromSessionCookie( $this->guid );
                mkdir( $directory, 0755, true );

                $filePath = Utils::uploadDirFromSessionCookie( $this->guid, $fileName );
                $saved = file_put_contents( $filePath, $httpRequest->getResponseBody() );

                if ( $saved !== FALSE ) {
                    $fileHash = sha1_file( $filePath );

                    $this->addFileToSession( $fileId, $fileName, $fileHash );

                    $this->doConversion( $fileName );
                } else {
                    throw new Exception( 'Error when saving file.' );
                }
            } else {
                throw new Exception( 'Error when downloading file.' );
            }
        } else {
            throw new Exception( 'Unable to get the file URL.' );
        }
    }

    private function addFileToSession( $fileId, $fileName, $fileHash ) {
        if( !isset( $_SESSION[ GDrive::SESSION_FILE_LIST ] )
                || !is_array( $_SESSION[ GDrive::SESSION_FILE_LIST ] ) ) {
            $_SESSION[ GDrive::SESSION_FILE_LIST ] = array();
        }

        $_SESSION[ GDrive::SESSION_FILE_LIST ][ $fileId ] = array(
            GDrive::SESSION_FILE_NAME => $fileName,
            GDrive::SESSION_FILE_HASH => $fileHash
        );
    }

    private function doConversion( $file_name ) {
        $uploadDir = $this->guid;

        $intDir         = INIT::$UPLOAD_REPOSITORY . 
            DIRECTORY_SEPARATOR . $uploadDir;

        $errDir         = INIT::$STORAGE_DIR .
            DIRECTORY_SEPARATOR .
            'conversion_errors'  . 
            DIRECTORY_SEPARATOR . $uploadDir;

        $conversionHandler = new ConversionHandler();
        $conversionHandler->setFileName( $file_name );
        $conversionHandler->setSourceLang( $this->source_lang );
        $conversionHandler->setTargetLang( $this->target_lang );
        $conversionHandler->setSegmentationRule( $this->seg_rule );
        $conversionHandler->setCookieDir( $uploadDir );
        $conversionHandler->setIntDir( $intDir );
        $conversionHandler->setErrDir( $errDir ); 

        $conversionHandler->doAction();

        return $conversionHandler->getResult();
    }

    private function correctSourceTargetLang() {
        if ( isset ( $_COOKIE[ GDrive::COOKIE_SOURCE_LANG ] ) ) {
            if( $_COOKIE[ GDrive::COOKIE_SOURCE_LANG ] != GDrive::EMPTY_VAL ) {
                $sourceLangHistory   = $_COOKIE[ GDrive::COOKIE_SOURCE_LANG ];
                $sourceLangAr        = explode( '||', urldecode( $sourceLangHistory ) );
                
                if(count( $sourceLangAr ) > 0) {
                    $this->source_lang = $sourceLangAr[0];
                }
            }
        } else {
            setcookie( GDrive::COOKIE_SOURCE_LANG, GDrive::EMPTY_VAL, time() + ( 86400 * 365 ) );
        }
        
        if ( isset ( $_COOKIE[ GDrive::COOKIE_TARGET_LANG ] ) ) {
            if( $_COOKIE[ GDrive::COOKIE_TARGET_LANG ] != GDrive::EMPTY_VAL ) {
                $targetLangHistory   = $_COOKIE[ GDrive::COOKIE_TARGET_LANG ];
                $targetLangAr        = explode( '||', urldecode( $targetLangHistory ) );
                
                if(count( $targetLangAr ) > 0) {
                    $this->target_lang = $targetLangAr[0];
                }
            }
        } else {
            setcookie( GDrive::COOKIE_TARGET_LANG, GDrive::EMPTY_VAL, time() + ( 86400 * 365 ) );
        }
        
        $_SESSION[ GDrive::SESSION_ACTUAL_SOURCE_LANG ] = $this->source_lang;
    }
    
    private function doRedirect() {
        header("Location: /?gdrive=1", true, 302);
        exit;
    }

    protected function afterConstruct() {

    }

}
