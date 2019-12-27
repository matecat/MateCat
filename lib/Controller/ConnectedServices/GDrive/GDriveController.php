<?php

namespace ConnectedServices\GDrive;

use API\V2\KleinController;
use Bootstrap;
use Constants;
use Exception;
use Utils;

class GDriveController extends KleinController {

    private $source_lang = Constants::DEFAULT_SOURCE_LANG;
    private $target_lang = Constants::DEFAULT_TARGET_LANG;
    private $seg_rule    = null;

    private $guid = null;

    private $isAsyncReq;

    private $isImportingSuccessful = true;

    /**
     * @var Session
     */
    private $gdriveUserSession;

    /**
     * @var RemoteFileService
     */
    private $gdriveConnectedService;

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

            $this->gdriveUserSession->clearFiles();
        }

        $listOfIds = [];

        if ( array_key_exists( 'ids', $state ) ) {
            $listOfIds = $state[ 'ids' ];
        } else {
            if ( array_key_exists( 'exportIds', $state ) ) {
                $listOfIds = $state[ 'exportIds' ];
            } else {
                throw new Exception( " no ids or export ids found " );
            }
        }

        $countIds = count( $listOfIds );

        $this->gdriveUserSession->setConversionParams( $this->guid, $this->source_lang, $this->target_lang, $this->seg_rule );

        for ( $i = 0; $i < $countIds && $this->isImportingSuccessful === true; $i++ ) {
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