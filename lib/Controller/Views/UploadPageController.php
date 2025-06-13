<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 26/05/25
 * Time: 18:10
 *
 */

namespace Views;

use AbstractControllers\BaseKleinViewController;
use ConnectedServices\GDrive\GDriveController;
use Constants;
use CookieManager;
use Engines_Intento;
use Exception;
use INIT;
use Langs\LanguageDomains;
use ProjectOptionsSanitizer;
use Utils;

class UploadPageController extends BaseKleinViewController {

    /**
     * @throws Exception
     */
    protected function renderView() {

        $guid = $this->checkDriveFilesOrGetGuid();
        if ( !empty( $guid ) ) {
            $this->initUploadDir( $guid );
        }

        $this->setEmptyCookieValueIfNoHistory( Constants::COOKIE_SOURCE_LANG );
        $this->setEmptyCookieValueIfNoHistory( Constants::COOKIE_TARGET_LANG );

        $this->setView( 'upload.html', [
                'conversion_enabled'                    => !empty( INIT::$FILTERS_ADDRESS ),
                'volume_analysis_enabled'               => INIT::$VOLUME_ANALYSIS_ENABLED,
                'maxFileSize'                           => INIT::$MAX_UPLOAD_FILE_SIZE,
                'maxTMXFileSize'                        => INIT::$MAX_UPLOAD_TMX_FILE_SIZE,
                'maxNumberFiles'                        => INIT::$MAX_NUM_FILES,
                'subjects'                              => json_encode( LanguageDomains::getInstance()->getEnabledDomains() ),
                'formats_number'                        => $this->countSupportedFileTypes(),
                'translation_engines_intento_prov_json' => str_replace( "\\\"", "\\\\\\\"", json_encode( Engines_Intento::getProviderList() ) ), // needed by JSON.parse() function
                'tag_projection_languages'              => json_encode( ProjectOptionsSanitizer::$tag_projection_allowed_languages ),
                'developerKey'                          => INIT::$GOOGLE_OAUTH_BROWSER_API_KEY,
                'clientId'                              => INIT::$GOOGLE_OAUTH_CLIENT_ID
        ] );

        if ( INIT::$LXQ_LICENSE ) {
            $this->addParamsToView( [
                            'lxq_license'      => INIT::$LXQ_LICENSE,
                            'lxq_partnerid'    => INIT::$LXQ_PARTNERID,
                            'lexiqa_languages' => json_encode( ProjectOptionsSanitizer::$lexiQA_allowed_languages ),
                            'lexiqaServer'     => INIT::$LXQ_SERVER,
                    ]
            );
        }

        $this->render();

    }

    /**
     * @throws Exception
     */
    private function checkDriveFilesOrGetGuid(): ?string {
        $guid = null;
        // If isset the GDRIVE_LIST_COOKIE_NAME cookie, do nothing
        if ( !isset( $_COOKIE[ GDriveController::GDRIVE_LIST_COOKIE_NAME ] ) ) {

            // Get the guid from the guid if it exists, otherwise set the guid into the cookie
            if ( !empty( $_COOKIE[ 'upload_token' ] ) && Utils::isTokenValid( $_COOKIE[ 'upload_token' ] ) ) {
                Utils::deleteDir( INIT::$UPLOAD_REPOSITORY . '/' . $_COOKIE[ 'upload_token' ] . '/' );
            }

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
        }

        return $guid;
    }

    /**
     * @param string $cookieName
     *
     * @return void
     */
    private function setEmptyCookieValueIfNoHistory( string $cookieName ) {
        if ( !isset( $_COOKIE[ $cookieName ] ) ) {
            CookieManager::setCookie( $cookieName, Constants::EMPTY_VAL,
                    [
                            'expires'  => time() + ( 86400 * 365 ),
                            'path'     => '/',
                            'domain'   => INIT::$COOKIE_DOMAIN,
                            'secure'   => true,
                            'httponly' => true,
                            'samesite' => 'None',
                    ]
            );
        }
    }

    /**
     * @param string $guid
     *
     * @return void
     */
    private function initUploadDir( string $guid ): void {
        $intDir = INIT::$UPLOAD_REPOSITORY . '/' . $guid . '/';
        if ( !is_dir( $intDir ) ) {
            mkdir( $intDir, 0775, true );
        }
    }

    /**
     * @return int
     */
    private function countSupportedFileTypes(): int {
        $count = 0;
        foreach ( INIT::$SUPPORTED_FILE_TYPES as $value ) {
            $count += count( $value );
        }

        return $count;
    }

}