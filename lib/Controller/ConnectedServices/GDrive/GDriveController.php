<?php

namespace ConnectedServices\GDrive;

use AbstractControllers\AbstractStatefulKleinController;
use Aws\S3\Exception\S3Exception;
use ConnectedServices\Google\GDrive\Session;
use ConnectedServices\Google\GoogleProvider;
use Constants;
use CookieManager;
use Exception;
use Filters\FiltersConfigTemplateDao;
use Filters\FiltersConfigTemplateStruct;
use Google_Service_Exception;
use INIT;
use Langs\InvalidLanguageException;
use Langs\Languages;
use Log;
use Utils;

class GDriveController extends AbstractStatefulKleinController {

    const GDRIVE_LIST_COOKIE_NAME    = 'gdrive_files_to_be_listed';
    const GDRIVE_OUTCOME_COOKIE_NAME = 'gdrive_files_outcome';

    private string                       $source_lang                   = Constants::DEFAULT_SOURCE_LANG;
    private string                       $target_lang                   = Constants::DEFAULT_TARGET_LANG;
    private ?string                      $segmentation_rule             = null;
    private ?FiltersConfigTemplateStruct $filters_extraction_parameters = null;
    private bool                         $isAsyncReq                    = true;
    private bool                         $isImportingSuccessful         = true;

    /**
     * @var Session
     */
    private Session $gdriveUserSession;

    /**
     * @var array
     */
    private array $error = [];

    /**
     * @throws Exception
     */
    public function open() {

        $filtersTemplateString = filter_var( $this->request->param( 'filters_extraction_parameters_template' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW ] );
        $filtersTemplateId     = filter_var( $this->request->param( 'filters_extraction_parameters_template_id' ), FILTER_VALIDATE_INT );
        $this->isAsyncReq      = filter_var( $this->request->param( 'isAsync' ), FILTER_VALIDATE_BOOLEAN );

        try {

            $this->segmentation_rule = Constants::validateSegmentationRules( $this->request->param( 'segmentation_rule' ) );

            $this->source_lang = $this->getValidSourceLanguage();
            $this->target_lang = $this->getValidTargetLanguages();

            if ( !empty( $filtersTemplateString ) ) {

                $filtersTemplate = new FiltersConfigTemplateStruct();
                $filtersTemplate->hydrateFromJSON( $filtersTemplateString );

                if ( $filtersTemplate === null ) {
                    throw new Exception( "filters_extraction_parameters_template not valid" );
                }

                $this->filters_extraction_parameters = $filtersTemplate;

            } elseif ( !empty( $filtersTemplateId ) ) {
                $filtersTemplate = FiltersConfigTemplateDao::getByIdAndUser( $filtersTemplateId, $this->getUser()->uid );

                if ( empty( $filtersTemplate ) ) {
                    throw new Exception( "filters_extraction_parameters_template_id not valid" );
                }

                $this->filters_extraction_parameters = $filtersTemplate;
            }

        } catch ( Exception $e ) {

            $this->isImportingSuccessful = false;
            $this->error                 = [
                    'code'  => $e->getCode(),
                    'class' => get_class( $e ),
                    'msg'   => $this->getExceptionMessage( $e )
            ];

            return;
        }

        $_SESSION[ Constants::SESSION_ACTUAL_SOURCE_LANG ] = $this->source_lang;

        $this->doImport();
        $this->finalize();

    }

    /**
     * @return string
     * @throws InvalidLanguageException
     */
    private function getValidSourceLanguage(): string {

        $sLang           = null;
        $languageHandler = Languages::getInstance();

        if ( !empty( $this->request->param( 'target' ) ) ) {
            $sLang = $languageHandler->validateLanguageListAsString( $this->request->param( 'source' ) );
        } elseif ( !empty( $_COOKIE[ Constants::COOKIE_SOURCE_LANG ] ) && Constants::EMPTY_VAL !== $_COOKIE[ Constants::COOKIE_SOURCE_LANG ] ) {
            $sLang = $languageHandler->validateLanguageListAsString( $_COOKIE[ Constants::COOKIE_SOURCE_LANG ] );
        }

        return $sLang ?? Constants::DEFAULT_SOURCE_LANG;

    }

    /**
     * @return string
     * @throws InvalidLanguageException
     */
    private function getValidTargetLanguages(): string {

        $tLang           = null;
        $languageHandler = Languages::getInstance();

        if ( !empty( $this->request->param( 'target' ) ) ) {
            $tLang = $languageHandler->validateLanguageListAsString( $this->request->param( 'target' ) );
        } elseif ( !empty( $_COOKIE[ Constants::COOKIE_TARGET_LANG ] ) && Constants::EMPTY_VAL !== $_COOKIE[ Constants::COOKIE_TARGET_LANG ] ) {
            $tLang = $languageHandler->validateLanguageListAsString( $_COOKIE[ Constants::COOKIE_TARGET_LANG ], '||' );
        }

        return $tLang ?? Constants::DEFAULT_TARGET_LANG;
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

        // set the upload directory name if there are files from gDrive
        if ( !$this->isAsyncReq ) {
            $guid = Utils::uuid4();
            CookieManager::setCookie( "upload_token", $guid,
                    [
                            'expires'  => time() + 86400,
                            'path'     => '/',
                            'domain'   => INIT::$COOKIE_DOMAIN,
                            'secure'   => true,
                            'httponly' => true,
                            'samesite' => 'Strict',
                    ]
            );
            $_SESSION[ "upload_token" ] = $_COOKIE[ "upload_token" ] = $guid;
            $this->gdriveUserSession->clearFileListFromSession();
        }

        $guid = $_SESSION[ "upload_token" ] = $_COOKIE[ "upload_token" ];

        if ( array_key_exists( 'ids', $state ) ) {
            $listOfIds = $state[ 'ids' ];
        } else {
            if ( array_key_exists( 'exportIds', $state ) ) {
                $listOfIds = $state[ 'exportIds' ];
            } else {
                throw new Exception( " no ids or export ids found " );
            }
        }

        $this->gdriveUserSession->setConversionParams( $guid, $this->source_lang, $this->target_lang, $this->segmentation_rule, $this->filters_extraction_parameters );

        for ( $i = 0; $i < count( $listOfIds ) && $this->isImportingSuccessful === true; $i++ ) {
            try {
                $client = GoogleProvider::getClient( INIT::$HTTPHOST . "/gdrive/oauth/response" );
                $this->gdriveUserSession->importFile( $listOfIds[ $i ], $client );
            } catch ( Exception $e ) {
                $this->isImportingSuccessful = false;
                $this->error                 = [
                        'code'  => $e->getCode(),
                        'class' => get_class( $e ),
                        'msg'   => $this->getExceptionMessage( $e )
                ];
                break;
            }
        }

        $_SESSION[ "gdrive_session" ] = $this->gdriveUserSession->getSession();
    }

    /**
     * @param Exception $e
     *
     * @return string
     */
    private function getExceptionMessage( Exception $e ): string {
        $rawMessage = $e->getMessage();

        // parse Google APIs errors
        if ( $e instanceof Google_Service_Exception and $jsonDecodedMessage = json_decode( $rawMessage, true ) ) {
            if ( isset( $jsonDecodedMessage[ 'error' ][ 'message' ] ) ) {
                return $jsonDecodedMessage[ 'error' ][ 'message' ];
            }

            if ( isset( $jsonDecodedMessage[ 'error' ][ 'errors' ] ) ) {
                $arrayMsg = [];

                foreach ( $jsonDecodedMessage[ 'error' ][ 'errors' ] as $error ) {
                    $arrayMsg[] = $error[ 'message' ];
                }

                return implode( ',', $arrayMsg );
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
                "success"     => $this->isImportingSuccessful,
                "error_msg"   => isset( $this->error[ 'msg' ] ) ? $this->formatErrorMessage( $this->error[ 'msg' ] ) : null,
                "error_class" => $this->error[ 'class' ] ?? null,
                "error_code"  => $this->error[ 'code' ] ?? null,
        ];

        CookieManager::setCookie( self::GDRIVE_OUTCOME_COOKIE_NAME, json_encode( $outcome ),
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
        CookieManager::setCookie( self::GDRIVE_LIST_COOKIE_NAME, $_SESSION[ "upload_token" ],
                [
                        'expires'  => time() + 86400,
                        'path'     => '/',
                        'domain'   => INIT::$COOKIE_DOMAIN,
                        'secure'   => true,
                        'httponly' => true,
                        'samesite' => 'Strict',
                ]
        );

        header( "Location: /", true, 302 );
        exit;
    }

    private function doResponse() {
        $this->response->json( [
                "success"     => $this->isImportingSuccessful,
                "error_msg"   => isset( $this->error[ 'msg' ] ) ? $this->formatErrorMessage( $this->error[ 'msg' ] ) : null,
                "error_class" => $this->error[ 'class' ] ?? null,
                "error_code"  => $this->error[ 'code' ] ?? null,
        ] );
    }

    /**
     * @param $message
     *
     * @return string
     */
    private function formatErrorMessage( $message ): string {

        if ( $message == "This file is too large to be exported." ) {
            return "you are trying to upload a file bigger than 10 mb. Google Drive does not allow exports of files bigger than 10 mb. Please download the file and upload it from your computer.";
        }

        if ( $message == "Export only supports Docs Editors files." ) {
            return "Google Drive does not allow exports of files in this format. Please open the file in Google Drive and save it as a Google Drive file.";
        }

        if ( strpos( $message, 'The specified key does not exist.' ) !== false ) {
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
        } catch ( S3Exception $e ) {

            $errorCode = 400;
            $this->response->code( $errorCode );
            $this->response->json( [
                    'code'  => $errorCode,
                    'class' => get_class( $e ),
                    'msg'   => $this->formatErrorMessage( $this->getExceptionMessage( $e ) )
            ] );
        } catch ( Exception $e ) {

            $errorCode = $e->getCode() >= 400 ? $e->getCode() : 500;
            $this->response->code( $errorCode );
            $this->response->json( [
                    'code'  => $errorCode,
                    'class' => get_class( $e ),
                    'msg'   => $this->formatErrorMessage( $this->getExceptionMessage( $e ) )
            ] );
        }

    }

    /**
     * @throws Exception
     */
    public function changeConversionParameters() {
        $originalSourceLang             = $_SESSION[ Constants::SESSION_ACTUAL_SOURCE_LANG ];
        $newSourceLang                  = filter_var( $this->request->param( 'source' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW ] );
        $newSegmentationRule            = filter_var( $this->request->param( 'segmentation_rule' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW ] );
        $newFiltersExtractionTemplate   = filter_var( $this->request->param( 'filters_extraction_parameters_template' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW ] );
        $newFiltersExtractionTemplateId = filter_var( $this->request->param( 'filters_extraction_parameters_template_id' ), FILTER_VALIDATE_INT );

        $filtersExtractionParameters = null;

        try {

            $languageHandler = Languages::getInstance();
            $newSourceLang   = $languageHandler->validateLanguage( $newSourceLang );

            if ( !empty( $newFiltersExtractionTemplate ) ) {

                $filtersExtractionParameters = new FiltersConfigTemplateStruct();
                $filtersExtractionParameters->hydrateFromJSON( $newFiltersExtractionTemplate );

                if ( $filtersExtractionParameters === null ) {
                    throw new Exception( "filters_extraction_parameters_template not valid" );
                }

            } elseif ( !empty( $newFiltersExtractionTemplateId ) ) {

                $filtersExtractionParameters = FiltersConfigTemplateDao::getByIdAndUser( $newFiltersExtractionTemplateId, $this->getUser()->uid );

                if ( $filtersExtractionParameters === null ) {
                    throw new Exception( "filters_extraction_parameters_template_id not valid" );
                }

            }

            $newSegmentationRule = Constants::validateSegmentationRules( $newSegmentationRule );

        } catch ( Exception $e ) {

            $this->isImportingSuccessful = false;
            $this->error                 = [
                    'code'  => $e->getCode(),
                    'class' => get_class( $e ),
                    'msg'   => $this->getExceptionMessage( $e )
            ];

            return;
        }

        $success = $this->gdriveUserSession->reConvert( $newSourceLang, $newSegmentationRule, $filtersExtractionParameters );

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

        $fileId           = $this->request->param( 'fileId' );
        $segmentationRule = $this->request->param( 'segmentation_rule' );
        $source           = $this->request->param( 'source' );
        $filtersTemplate  = $this->request->param( 'filters_template' );

        if ( $fileId === 'all' ) {
            $this->gdriveUserSession->removeAllFiles( $source, $segmentationRule, $filtersTemplate );
            $success = true;
            unset( $_SESSION[ "gdrive_session" ] );
        } else {
            $success = $this->gdriveUserSession->removeFile( $fileId, $source, $segmentationRule, $filtersTemplate );

            if ( $success ) {
                if ( !$this->gdriveUserSession->hasFiles() ) {
                    unset( $_SESSION[ "gdrive_session" ] );
                } else {
                    unset( $_SESSION[ "gdrive_session" ][ Session::FILE_LIST ][ $fileId ] );
                }
            }
        }

        $this->response->json( [
                "success" => $success
        ] );
    }

    /**
     * @throws Exception
     */
    protected function afterConstruct() {
        $this->initSessionService();
    }

}