<?php


namespace ConnectedServices\GDrive ;

use Bootstrap ;
use Log;
use API\V2\KleinController ;
use Utils ;
use INIT ;
use ConversionHandler ;
use Constants;
use Exception;

class GDriveController extends KleinController {

    private $source_lang = Constants::DEFAULT_SOURCE_LANG;
    private $target_lang = Constants::DEFAULT_TARGET_LANG;
    private $seg_rule = null;

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
    private $gdriveConnectedService ;

    public function open() {

        $this->setIsAsyncReq( $this->request->param('isAsync') );

        $this->correctSourceTargetLang();

        $this->doImport();

        $this->finalize();

    }

    private function initSessionService() {
        $this->gdriveUserSession = new Session( $_SESSION ) ;
    }

    private function doImport() {

        $state = json_decode( $this->request->param('state'), TRUE );
        \Log::doLog( $state );

        // TODO: check why this is necessary here.
        if ( $this->isAsyncReq && $this->gdriveUserSession->hasFiles() ) {
            $this->guid = $_SESSION[ "upload_session" ];
        } else {
            $this->guid = Utils::createToken();
            setcookie( "upload_session", $this->guid, time() + 86400, '/' );
            $_SESSION[ "upload_session" ] = $this->guid;

            $this->gdriveUserSession->clearFiles();
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

        $this->gdriveUserSession->setConversionParams($this->guid, $this->source_lang, $this->target_lang, $this->seg_rule) ;

        for( $i = 0; $i < $countIds && $this->isImportingSuccessful === true; $i++ ) {
            $this->gdriveUserSession->importFile( $listOfIds[$i] );
        }
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

                if (count( $targetLangAr ) > 0) {
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
        $response = $this->gdriveUserSession->getFileStructureForJsonOutput();
        $this->response->json($response);
    }

    public function changeSourceLanguage() {
        $originalSourceLang = $_SESSION[ Constants::SESSION_ACTUAL_SOURCE_LANG ];
        $newSourceLang = $this->request->sourceLanguage;

        $success = $this->gdriveUserSession->changeSourceLanguage( $newSourceLang, $originalSourceLang );

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

    public function deleteImportedFile() {
        $fileId = $this->request->fileId;
        $success = false;

        if ( $fileId === 'all' ) {
            $this->gdriveUserSession->removeAllFiles() ;
            $success = true;
        } else {
            $success = $this->gdriveUserSession->removeFile( $fileId );
        }

        $this->response->json( array(
            "success" => $success
        ));
    }

    protected function afterConstruct() {
        Bootstrap::sessionStart();
        $this->initSessionService();
    }

    private function setIsAsyncReq( $isAsyncReq ) {
        if( $isAsyncReq === 'true' ) {
            $this->isAsyncReq = true;
        } else {
            $this->isAsyncReq = false;
        }
    }

}