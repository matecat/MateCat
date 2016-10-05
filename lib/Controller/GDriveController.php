<?php

use Bootstrap ;
use Log;
use API\V2\KleinController ;
use Google_Http_Request ;
use Utils ;
use INIT ;
use ConversionHandler ;
use GDrive;
use Constants;
use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class GDriveController extends KleinController {

    private $source_lang = Constants::DEFAULT_SOURCE_LANG;
    private $target_lang = Constants::DEFAULT_TARGET_LANG;
    private $seg_rule = null;

    private $gdriveService = null;

    private $guid = null;

    private $isAsyncReq;

    private $isImportingSuccessful = true;

    public function open() {

        $this->setIsAsyncReq( $this->request->param('isAsync') );

        $this->doAuth();

        if( $this->gdriveService != null ) {
            $this->correctSourceTargetLang();

            $this->doImport();

            if( $this->isImportingSuccessful ) {
                $this->finalize();
            } else {
                $this->redirectToLogin();
            }
        } else {
            $this->redirectToLogin();
        }
    }

    private function doAuth() {
        $this->gdriveService = GDrive::getService( array( 'uid' => $_SESSION[ 'uid' ] ) );
    }

    private function doImport() {

        $state = json_decode( $this->request->param('state'), TRUE );

        \Log::doLog( $state );

        if( $this->isAsyncReq && GDrive::sessionHasFiles( $_SESSION )) {
            $this->guid = $_SESSION[ "upload_session" ];
        } else {
            $this->guid = Utils::create_guid();
            setcookie( "upload_session", $this->guid, time() + 86400, '/' );
            $_SESSION[ "upload_session" ] = $this->guid;
            unset( $_SESSION[ GDrive::SESSION_FILE_LIST ] );
        }

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

        for( $i = 0; $i < $countIds && $this->isImportingSuccessful === true; $i++ ) {
            $this->importFile( $listOfIds[$i] );
        }
    }

    private function importFile( $fileId ) {
        try {
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

                $fileName = $this->sanetizeFileName( $file->getTitle() );
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

                    if( !is_dir( $directory ) ) {
                        mkdir( $directory, 0755, true );
                    }

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
        } catch (Exception $e) {
            \Log::doLog( $e->getMessage() );
            $this->isImportingSuccessful = false;
        }
    }

    private function sanetizeFileName( $fileName ) {
        return str_replace('/', '_', $fileName);
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
        if ( isset ( $_COOKIE[ Constants::COOKIE_SOURCE_LANG ] ) ) {
            if( $_COOKIE[ Constants::COOKIE_SOURCE_LANG ] != Constants::EMPTY_VAL ) {
                $sourceLangHistory   = $_COOKIE[ Constants::COOKIE_SOURCE_LANG ];
                $sourceLangAr        = explode( '||', urldecode( $sourceLangHistory ) );

                if(count( $sourceLangAr ) > 0) {
                    $this->source_lang = $sourceLangAr[0];
                }
            }
        } else {
            setcookie( Constants::COOKIE_SOURCE_LANG, Constants::EMPTY_VAL, time() + ( 86400 * 365 ) );
        }

        if ( isset ( $_COOKIE[ Constants::COOKIE_TARGET_LANG ] ) ) {
            if( $_COOKIE[ Constants::COOKIE_TARGET_LANG ] != Constants::EMPTY_VAL ) {
                $targetLangHistory   = $_COOKIE[ Constants::COOKIE_TARGET_LANG ];
                $targetLangAr        = explode( '||', urldecode( $targetLangHistory ) );

                if(count( $targetLangAr ) > 0) {
                    $this->target_lang = $targetLangAr[0];
                }
            }
        } else {
            setcookie( Constants::COOKIE_TARGET_LANG, Constants::EMPTY_VAL, time() + ( 86400 * 365 ) );
        }

        $_SESSION[ Constants::SESSION_ACTUAL_SOURCE_LANG ] = $this->source_lang;
    }

    private function finalize() {
        if( $this->isAsyncReq ) {
            $this->doResponse();
        } else {
            $this->doRedirect();
        }
    }

    private function doRedirect() {
        header("Location: /", true, 302);
        exit;
    }

    private function doResponse() {
        $this->response->json( array(
            "success" => true
        ));
    }

    public function listImportedFiles() {
        $response = array();

        $fileList = $_SESSION[ GDrive::SESSION_FILE_LIST ];

        foreach ( $fileList as $fileId => $file) {
            $path = $this->getGDriveFilePath( $file );

            $fileName = $file[ GDrive::SESSION_FILE_NAME ];

            if(file_exists($path) !== false) {
                $fileSize = filesize($path);

                $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);

                $response[ 'files' ][] = array(
                    'fileId' => $fileId,
                    'fileName' => $fileName,
                    'fileSize' => $fileSize,
                    'fileExtension' => $fileExtension
                );
            } else {
                unset( $_SESSION[ GDrive::SESSION_FILE_LIST ][ $fileId ] );
            }
        }

        $this->response->json($response);
    }

    private function getGDriveFilePath( $file ) {
        $fileName = $file[ GDrive::SESSION_FILE_NAME ];

        $cacheFileDir = $this->getCacheFileDir( $file );

        $path = $cacheFileDir . DIRECTORY_SEPARATOR . "package" . DIRECTORY_SEPARATOR . "orig" . DIRECTORY_SEPARATOR . $fileName;

        return $path;
    }

    private function getCacheFileDir( $file, $lang = '' ){
        $sourceLang = $_SESSION[ Constants::SESSION_ACTUAL_SOURCE_LANG ];

        if( $lang !== '' ) {
            $sourceLang = $lang;
        }

        $fileHash = $file[ GDrive::SESSION_FILE_HASH ];

        $cacheTreeAr = array(
            'firstLevel'  => $fileHash{0} . $fileHash{1},
            'secondLevel' => $fileHash{2} . $fileHash{3},
            'thirdLevel'  => substr( $fileHash, 4 )
        );

        $cacheTree = implode(DIRECTORY_SEPARATOR, $cacheTreeAr);

        $cacheFileDir = INIT::$CACHE_REPOSITORY . DIRECTORY_SEPARATOR . $cacheTree . "|" . $sourceLang;

        return $cacheFileDir;
    }

    private function getUploadDir(){
        return INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . filter_input(INPUT_COOKIE, 'upload_session');
    }

    public function changeSourceLanguage() {
        $originalSourceLang = $_SESSION[ Constants::SESSION_ACTUAL_SOURCE_LANG ];

        $newSourceLang = $this->request->sourceLanguage;

        $fileList = $_SESSION[ GDrive::SESSION_FILE_LIST ];

        $success = true;

        foreach( $fileList as $fileId => $file ) {
            if($success) {
                $fileHash = $file[ GDrive::SESSION_FILE_HASH ];

                if($newSourceLang !== $originalSourceLang) {

                    $originalCacheFileDir = $this->getCacheFileDir( $file, $originalSourceLang );

                    $newCacheFileDir = $this->getCacheFileDir( $file, $newSourceLang );

                    $renameDirSuccess = rename($originalCacheFileDir, $newCacheFileDir);

                    $uploadDir = $this->getUploadDir();

                    $originalUploadRefFile = $uploadDir . DIRECTORY_SEPARATOR . $fileHash . '|' . $originalSourceLang;
                    $newUploadRefFile = $uploadDir . DIRECTORY_SEPARATOR . $fileHash . '|' . $newSourceLang;

                    $renameFileRefSuccess = rename($originalUploadRefFile, $newUploadRefFile);

                    if(!$renameDirSuccess || !$renameFileRefSuccess) {
                        Log::doLog('Error when moving cache file dir to ' . $newCacheFileDir);

                        $success = false;
                    }
                }
            }
        }

        if( $success ) {
            $_SESSION[ Constants::SESSION_ACTUAL_SOURCE_LANG ] = $newSourceLang;

            $ckSourceLang = filter_input(INPUT_COOKIE, Constants::COOKIE_SOURCE_LANG);

            if ( $ckSourceLang == null || $ckSourceLang === false || $ckSourceLang === Constants::EMPTY_VAL ) {
                $ckSourceLang = '';
            }

            $newCookieVal = $newSourceLang . '||' . $ckSourceLang;

            setcookie( Constants::COOKIE_SOURCE_LANG, $newCookieVal, time() + ( 86400 * 365 ), '/' );
        } else {
            $_SESSION[ Constants::SESSION_ACTUAL_SOURCE_LANG ] = $originalSourceLang;
        }

        $response = array(
            "success" => $success
        );

        $this->response->json($response);
    }

    private function deleteDirectory($dir) {
        $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);

        foreach($files as $file) {
            if ($file->isDir()){
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($dir);
    }

    public function deleteImportedFile() {
        $fileId = $this->request->fileId;

        $success = false;

        if( $fileId === 'all' ) {
            foreach( $_SESSION[ GDrive::SESSION_FILE_LIST ] as $singleFileId => $file ) {
                $this->deleteSingleFile( $singleFileId );
            }

            unset( $_SESSION[ GDrive::SESSION_FILE_LIST ] );

            $success = true;
        } else {
            $success = $this->deleteSingleFile( $fileId );
        }

        $this->response->json( array(
            "success" => $success
        ));
    }

    private function deleteSingleFile( $fileId ) {
        $success = false;

        if( isset( $_SESSION[ GDrive::SESSION_FILE_LIST ][ $fileId ] ) ) {
            $file = $_SESSION[ GDrive::SESSION_FILE_LIST ][ $fileId ];

            $pathCache = $this->getCacheFileDir( $file );

            $this->deleteDirectory($pathCache);

            unset( $_SESSION[ GDrive::SESSION_FILE_LIST ][ $fileId ] );

            Log::doLog( 'File ' . $fileId . ' removed.' );

            $success = true;
        }

        return  $success;
    }

    protected function afterConstruct() {
        Bootstrap::sessionStart();
    }

    private function setIsAsyncReq( $isAsyncReq ) {
        if( $isAsyncReq === 'true' ) {
            $this->isAsyncReq = true;
        } else {
            $this->isAsyncReq = false;
        }
    }

    private function redirectToLogin() {
        $_SESSION[ 'oauthScope' ] = 'GDrive';
        $_SESSION[ 'incomingUrl' ] = $this->request->uri();

        $this->response->redirect( '/login' );
    }

    public function isGDriveAccessible() {
        $message = "";
        $success = false;

        if( isset($_SESSION[ 'uid' ]) ) {
            try {
                $service = GDrive::getService( array( 'uid' => $_SESSION[ 'uid' ] ) );

                $about = $service->about->get();

                $message = "OK";

                $success = true;
            } catch (Exception $e) {
                $message = "An error occurred: " . $e->getMessage();
            }
        } else {
            $message = "Not logged in.";
        }

        $this->response->json(array(
            "success" => $success,
            "message" => $message
        ));
    }

}