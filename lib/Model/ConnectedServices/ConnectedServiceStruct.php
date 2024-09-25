<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 07/11/2016
 * Time: 16:50
 */

namespace ConnectedServices;


use Exception;

class ConnectedServiceStruct extends \DataAccess_AbstractDaoSilentStruct implements \DataAccess_IDaoStruct {

    public $id;
    public $uid;
    public $service;
    public $email;
    public $name;

    public $remote_id;

    public $oauth_access_token;

    public $created_at;
    public $updated_at;

    public $expired_at;
    public $disabled_at;

    public $is_default;

    /**
     * Returns the decoded access token.
     *
     * @return bool|string
     */
    public function getDecryptedOauthAccessToken() {
        $oauthTokenEncryption = OauthTokenEncryption::getInstance();

        return $oauthTokenEncryption->decrypt( $this->oauth_access_token );
    }

    /**
     * @param $token
     */
    public function setEncryptedAccessToken( $token ) {
        $oauthTokenEncryption     = OauthTokenEncryption::getInstance();
        $this->oauth_access_token = $oauthTokenEncryption->encrypt( $token );
    }

    /**
     * @param string|null $field
     *
     * @return ?array
     * @throws Exception
     */
    public function getDecodedOauthAccessToken( ?string $field = null ): ?array {
        $decoded = json_decode( $this->getDecryptedOauthAccessToken(), true );

        if ( $field ) {
            if ( array_key_exists( $field, $decoded ) ) {
                return $decoded[ $field ];
            } else {
                throw new Exception( 'key not found on token: ' . $field );
            }
        }

        return $decoded;
    }

}