<?php

namespace ConnectedServices\GDrive;

use API\V2\KleinController;
use Bootstrap;
use Constants;
use Exception;
use Utils;

class GDriveController extends KleinController {

    private $gdriveListCookieName  = "gdrive_files_to_be_listed";
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
     * @throws Exception
     */
    public function open() {
        $this->setIsAsyncReq( $this->request->param( 'isAsync' ) );
        $this->source_lang = $this->request->param( 'source' );
        $this->target_lang = $this->request->param( 'target' );

        $_SESSION[ Constants::SESSION_ACTUAL_SOURCE_LANG ] = $this->source_lang;

        $this->doImport();
        $this->finalize();
    }

    //https://dev.matecat.com/webhooks/gdrive/open?state=%7B%22exportIds%22:%5B%221XetJOhXJLUSGX8Jslj3NozkT_3o4kznit4LbjmuWIQ4%22%5D,%22action%22:%22open%22,%22userId%22:%22114613229066836367499%22%7D

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
        \Log::doJsonLog( $state );

        // TODO: check why this is necessary here.
        if ( $this->isAsyncReq && $this->gdriveUserSession->hasFiles() ) {
            $this->guid = $_SESSION[ "upload_session" ];
        } else {
            $this->guid = Utils::createToken();
            setcookie( "upload_session", $this->guid, time() + 86400, '/', \INIT::$COOKIE_DOMAIN );
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
            $this->gdriveUserSession->importFile( $listOfIds[ $i ] );
        }
    }

    private function finalize() {
        if ( $this->isAsyncReq ) {
            $this->doResponse();
        } else {
            $this->doRedirect();
        }
    }

    private function doRedirect() {
        // set a cookie to allow the frontend to call list endpoint
        setcookie( $this->gdriveListCookieName, $_SESSION[ "upload_session" ], time() + 86400, '/', \INIT::$COOKIE_DOMAIN );

        header( "Location: /", true, 302 );
        exit;
    }

    private function doResponse() {
        $this->response->json( [
                "success" => true
        ] );
    }

    /**
     * @throws Exception
     */
    public function listImportedFiles() {
        $response = $this->gdriveUserSession->getFileStructureForJsonOutput();
        $this->response->json( $response );

        // delete the cookie
        setcookie( $this->gdriveListCookieName, "", time() - 3600 );
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
     * @throws \Exception
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