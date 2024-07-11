<?php

namespace ConnectedServices\GDrive;

use API\V2\KleinController;
use Aws\S3\Exception\S3Exception;
use Bootstrap;
use Constants;
use CookieManager;
use Exception;
use Google_Service_Exception;
use INIT;
use Log;
use Utils;

class GDriveController extends KleinController {

    const GDRIVE_LIST_COOKIE_NAME = 'gdrive_files_to_be_listed';
    const GDRIVE_OUTCOME_COOKIE_NAME = 'gdrive_files_outcome';

    private $source_lang           = Constants::DEFAULT_SOURCE_LANG;
    private $target_lang           = Constants::DEFAULT_TARGET_LANG;
    private $seg_rule              = null;
    private $guid                  = null;
    private $isAsyncReq;
    private $isImportingSuccessful = true;

    /**
     * @var Session
     */
    private $gdriveUserSession;

    /**
     * @var array
     */
    private $error;

    /**
     * @throws Exception
     */
    public function open() {
        $this->setIsAsyncReq( $this->request->param( 'isAsync' ) );
        $this->source_lang = $this->getSource();
        $this->target_lang = $this->getTarget();

        $_SESSION[ Constants::SESSION_ACTUAL_SOURCE_LANG ] = $this->source_lang;

        $this->doImport();
        $this->finalize();
    }

    /**
     * @return string
     */
    private function getSource() {

        if ( null !== $this->request->param( 'source' ) ) {
            return $this->request->param( 'source' );
        }

        if ( isset( $_COOKIE[ Constants::COOKIE_SOURCE_LANG ] ) and null !== $_COOKIE[ Constants::COOKIE_SOURCE_LANG ] and Constants::EMPTY_VAL !== $_COOKIE[ Constants::COOKIE_SOURCE_LANG ] ) {
            $cookieSource = explode('||', $_COOKIE[ Constants::COOKIE_SOURCE_LANG ]);

            return $cookieSource[ 0 ];
        }

        return Constants::DEFAULT_SOURCE_LANG;
    }

    /**
     * @return string
     */
    private function getTarget() {

        if ( null !== $this->request->param( 'target' ) ) {
            return $this->request->param( 'target' );
        }

        if ( isset( $_COOKIE[ Constants::COOKIE_TARGET_LANG ] ) and null !== $_COOKIE[ Constants::COOKIE_TARGET_LANG ] and Constants::EMPTY_VAL !== $_COOKIE[ Constants::COOKIE_TARGET_LANG ] ) {
            $cookieTarget = explode('||', $_COOKIE[ Constants::COOKIE_TARGET_LANG ]);

            return implode(",", $cookieTarget);
        }

        return Constants::DEFAULT_TARGET_LANG;
    }

    /**
     * @throws Exception
     */
    private function initSessionService() {
        $this->gdriveUserSession = new Session();
    }

    /**
     * @throws Exception
     */
    private function doImport() {

        $state = json_decode( $this->request->param( 'state' ), true );
        Log::doJsonLog( $state );

        // TODO: check why this is necessary here.
        if ( $this->isAsyncReq && $this->gdriveUserSession->hasFiles() ) {
            $this->guid = $_SESSION[ "upload_session" ];
        } else {
            $this->guid = Utils::uuid4();
            CookieManager::setCookie( "upload_session", $this->guid,
                [
                    'expires'  => time() + 86400,
                    'path'     => '/',
                    'domain'   => INIT::$COOKIE_DOMAIN,
                    'secure'   => true,
                    'httponly' => false,
                    'samesite' => 'None',
                ]
            );
            $_SESSION[ "upload_session" ] = $this->guid;

            $this->gdriveUserSession->clearFileListFromSession();
        }

        if ( array_key_exists( 'ids', $state ) ) {
            $listOfIds = $state[ 'ids' ];
        } else {
            if ( array_key_exists( 'exportIds', $state ) ) {
                $listOfIds = $state[ 'exportIds' ];
            } else {
                throw new Exception( " no ids or export ids found " );
            }
        }

        $this->gdriveUserSession->setConversionParams( $this->guid, $this->source_lang, $this->target_lang, $this->seg_rule );

        for ( $i = 0; $i < count( $listOfIds ) && $this->isImportingSuccessful === true; $i++ ) {
            try {
                $this->gdriveUserSession->importFile( $listOfIds[ $i ] );
            } catch ( Exception $e){
                $this->isImportingSuccessful = false;
                $this->error = [
                    'code' => $e->getCode(),
                    'class' => get_class($e),
                    'msg' => $this->getExceptionMessage($e)
                ];
                break;
            }
        }
    }

    /**
     * @param Exception $e
     *
     * @return string
     */
    private function getExceptionMessage( Exception $e){
        $rawMessage = $e->getMessage();

        // parse Google APIs errors
        if($e instanceof Google_Service_Exception and $jsonDecodedMessage = json_decode($rawMessage, true)) {
            if (isset($jsonDecodedMessage['error']['message'])) {
                return $jsonDecodedMessage['error']['message'];
            }

            if (isset($jsonDecodedMessage['error']['errors'])) {
                $arrayMsg = [];

                foreach ($jsonDecodedMessage['error']['errors'] as $error){
                    $arrayMsg[] = $error['message'];
                }

                return implode(',', $arrayMsg);
            }

            return $jsonDecodedMessage;
        }

        return $rawMessage;
    }

    private function finalize() {
        if ( $this->isAsyncReq ) {
            $this->doResponse();
        } else {
            $this->doRedirect();
        }
    }

    private function doRedirect() {

        // set a cookie for callback outcome to allow the frontend to show errors
        $outcome = [
            "success" => $this->isImportingSuccessful,
            "error_msg" => isset($this->error['msg']) ? $this->formatErrorMessage($this->error['msg']) : null,
            "error_class" => isset($this->error['class']) ? $this->error['class'] : null,
            "error_code" => isset($this->error['code']) ? $this->error['code'] : null,
        ];

        CookieManager::setCookie( self::GDRIVE_OUTCOME_COOKIE_NAME, json_encode($outcome),
            [
                'expires'  => time() + 86400,
                'path'     => '/',
                'domain'   => INIT::$COOKIE_DOMAIN,
                'secure'   => true,
                'httponly' => false,
                'samesite' => 'None',
            ]
        );

        // set a cookie to allow the frontend to call list endpoint
        CookieManager::setCookie( self::GDRIVE_LIST_COOKIE_NAME, $_SESSION[ "upload_session" ],
            [
                'expires'  => time() + 86400,
                'path'     => '/',
                'domain'   => INIT::$COOKIE_DOMAIN,
                'secure'   => true,
                'httponly' => false,
                'samesite' => 'None',
            ]
        );

        header( "Location: /", true, 302 );
        exit;
    }

    private function doResponse() {
        $this->response->json( [
            "success" => $this->isImportingSuccessful,
            "error_msg" => isset($this->error['msg']) ? $this->formatErrorMessage($this->error['msg']) : null,
            "error_class" => isset($this->error['class']) ? $this->error['class'] : null,
            "error_code" => isset($this->error['code']) ? $this->error['code'] : null,
        ] );
    }

    /**
     * @param $message
     * @return string
     */
    private function formatErrorMessage($message){

        if($message == "This file is too large to be exported."){
            return "you are trying to upload a file bigger than 10 mb. Google Drive does not allow exports of files bigger than 10 mb. Please download the file and upload it from your computer.";
        }

        if($message == "Export only supports Docs Editors files."){
            return "Google Drive does not allow exports of files in this format. Please open the file in Google Drive and save it as a Google Drive file.";
        }

        if (strpos($message, 'The specified key does not exist.') !== false) {
            return "The name of the file you are trying to upload is too long, please shorten it and try again.";
        }

        return $message;
    }

    /**
     * @throws Exception
     */
    public function listImportedFiles() {

        try {
            $response = $this->gdriveUserSession->getFileStructureForJsonOutput();
            $this->response->json( $response );

            // delete the cookie
            CookieManager::setCookie( self::GDRIVE_LIST_COOKIE_NAME, "",
                [
                    'expires'  => time() - 3600,
                    'path'     => '/',
                    'domain'   => INIT::$COOKIE_DOMAIN,
                    'secure'   => true,
                    'httponly' => false,
                    'samesite' => 'None',
                ]
            );
        } catch (S3Exception $e){

            $errorCode = 400;
            $this->response->code($errorCode);
            $this->response->json( [
                'code' => $errorCode,
                'class' => get_class($e),
                'msg' => $this->formatErrorMessage($this->getExceptionMessage($e))
            ] );
        } catch (Exception $e){

            $errorCode = $e->getCode() >= 400 ? $e->getCode() : 500;
            $this->response->code($errorCode);
            $this->response->json( [
                'code' => $errorCode,
                'class' => get_class($e),
                'msg' => $this->formatErrorMessage($this->getExceptionMessage($e))
            ] );
        }

    }

    /**
     * @throws Exception
     */
    public function changeSourceLanguage() {
        $originalSourceLang = $_SESSION[ Constants::SESSION_ACTUAL_SOURCE_LANG ];
        $newSourceLang      = $this->request->sourceLanguage;
        $success            = $this->gdriveUserSession->changeSourceLanguage( $newSourceLang, $originalSourceLang );

        if ( $success ) {
            $_SESSION[ Constants::SESSION_ACTUAL_SOURCE_LANG ] = $newSourceLang;
            $this->source_lang                                 = $newSourceLang;
        } else {
            $_SESSION[ Constants::SESSION_ACTUAL_SOURCE_LANG ] = $originalSourceLang;
            $this->source_lang                                 = $originalSourceLang;
        }

        $this->response->json( [
            "success" => $success
        ] );
    }

    /**
     * @throws Exception
     */
    public function deleteImportedFile() {
        $fileId  = $this->request->fileId;
        $success = false;

        if ( $fileId === 'all' ) {
            $this->gdriveUserSession->removeAllFiles();
            $success = true;
        } else {
            $success = $this->gdriveUserSession->removeFile( $fileId );
        }

        $this->response->json( [
            "success" => $success
        ] );
    }

    /**
     * @throws Exception
     */
    protected function afterConstruct() {
        Bootstrap::sessionStart();
        $this->initSessionService();
    }

    private function setIsAsyncReq( $isAsyncReq ) {
        if ( $isAsyncReq === 'true' ) {
            $this->isAsyncReq = true;
        } else {
            $this->isAsyncReq = false;
        }
    }

}