<?php

namespace Model\ConnectedServices\Oauth;

use Exception;
use Utils\Constants\Constants;
use Utils\Registry\AppConfig;

/**
 * Class OauthTokenEncryption.
 * This class is a singleton for DefuseEncryption to encrypt the user's OAuth Token
 */
class OauthTokenEncryption extends DefuseEncryption
{

    private static ?OauthTokenEncryption $instance = null;

    /**
     * @throws Exception
     */
    public static function getInstance(): OauthTokenEncryption
    {
        if (self::$instance === null) {
            self::$instance = new OauthTokenEncryption(
                AppConfig::$ROOT . Constants::OAUTH_TOKEN_KEY_FILE
            );
        }

        return self::$instance;
    }

}
