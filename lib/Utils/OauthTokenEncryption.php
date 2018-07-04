<?php

/**
 * Class OauthTokenEncryption.
 * This class is a singleton for DefuseEncryption to encrypt the user's OAuth Token
 */
class OauthTokenEncryption extends DefuseEncryption {

    private static $instance = null;

    /**
     * Singleton method to create a new instance of OauthTokenEncryption with the token key file.
     * @return null|OauthTokenEncryption
     */
    public static function getInstance() {
        if( self::$instance === null ){
            self::$instance = new OauthTokenEncryption(
                INIT::$ROOT . Constants::OAUTH_TOKEN_KEY_FILE
            );
        }

        return self::$instance;
    }

    /**
     * Check if a token is encrypted
     * @param $token
     * @return bool
     */
    public function isTokenEncrypted( $token ) {
        if( !empty( $token ) && !json_decode( $token ) && $this->decrypt( $token ) !== false ) {
            return true;
        }

        return false;
    }


}
