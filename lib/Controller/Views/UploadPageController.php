<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 26/05/25
 * Time: 18:10
 *
 */

namespace Controller\Views;

use Controller\Abstracts\Authentication\CookieManager;
use Controller\Abstracts\BaseKleinViewController;
use Controller\API\GDrive\GDriveController;
use Exception;
use Model\ProjectManager\ProjectOptionsSanitizer;
use Utils\Constants\Constants;
use Utils\Engines\Intento;
use Utils\Langs\LanguageDomains;
use Utils\Registry\AppConfig;
use Utils\Templating\PHPTalBoolean;
use Utils\Templating\PHPTalMap;
use Utils\Tools\Utils;

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
                'conversion_enabled'                    => new PHPTalBoolean( !empty( AppConfig::$FILTERS_ADDRESS ) ),
                'volume_analysis_enabled'               => new PHPTalBoolean( AppConfig::$VOLUME_ANALYSIS_ENABLED ),
                'maxFileSize'                           => AppConfig::$MAX_UPLOAD_FILE_SIZE,
                'maxTMXFileSize'                        => AppConfig::$MAX_UPLOAD_TMX_FILE_SIZE,
                'maxNumberFiles'                        => AppConfig::$MAX_NUM_FILES,
                'subjects'                              => new PHPTalMap( LanguageDomains::getInstance()->getEnabledDomains() ),
                'formats_number'                        => $this->countSupportedFileTypes(),
                'translation_engines_intento_prov_json' => new PHPTalMap( Intento::getProviderList() ),
                'tag_projection_languages'              => new PHPTalMap( ProjectOptionsSanitizer::$tag_projection_allowed_languages ),
                'developerKey'                          => AppConfig::$GOOGLE_OAUTH_BROWSER_API_KEY,
                'clientId'                              => AppConfig::$GOOGLE_OAUTH_CLIENT_ID
        ] );

        if ( AppConfig::$LXQ_LICENSE ) {
            $this->addParamsToView( [
                            'lxq_license'      => AppConfig::$LXQ_LICENSE,
                            'lxq_partnerid'    => AppConfig::$LXQ_PARTNERID,
                            'lexiqa_languages' => new PHPTalMap( ProjectOptionsSanitizer::$lexiQA_allowed_languages ),
                            'lexiqaServer'     => AppConfig::$LXQ_SERVER,
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
                Utils::deleteDir( AppConfig::$UPLOAD_REPOSITORY . '/' . $_COOKIE[ 'upload_token' ] . '/' );
            }

            $guid = Utils::uuid4();
            CookieManager::setCookie( "upload_token", $guid,
                    [
                            'expires'  => time() + 86400,
                            'path'     => '/',
                            'domain'   => AppConfig::$COOKIE_DOMAIN,
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
                            'domain'   => AppConfig::$COOKIE_DOMAIN,
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
        $intDir = AppConfig::$UPLOAD_REPOSITORY . '/' . $guid . '/';
        if ( !is_dir( $intDir ) ) {
            mkdir( $intDir, 0775, true );
        }
    }

    /**
     * @return int
     */
    private function countSupportedFileTypes(): int {
        $count = 0;
        foreach ( AppConfig::$SUPPORTED_FILE_TYPES as $value ) {
            $count += count( $value );
        }

        return $count;
    }

}