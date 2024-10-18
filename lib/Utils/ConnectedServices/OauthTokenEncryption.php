<?php

namespace ConnectedServices;

use Constants;
use DefuseEncryption;
use INIT;

/**
 * Class OauthTokenEncryption.
 * This class is a singleton for DefuseEncryption to encrypt the user's OAuth Token
 */
class OauthTokenEncryption extends DefuseEncryption {

    /**
     * @var OauthTokenEncryption|null
     */
    private static ?OauthTokenEncryption $instance = null;

    /**
     * Singleton method to create a new instance of OauthTokenEncryption with the token key file.
     * @return null|OauthTokenEncryption
     */
    public static function getInstance(): ?OauthTokenEncryption {
        if ( self::$instance === null ) {
            self::$instance = new OauthTokenEncryption(
                    INIT::$ROOT . Constants::OAUTH_TOKEN_KEY_FILE
            );
        }

        return self::$instance;
    }

}
