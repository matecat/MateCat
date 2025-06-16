<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 07/11/2016
 * Time: 16:50
 */

namespace ConnectedServices;


use DataAccess\AbstractDaoSilentStruct;
use DataAccess\IDaoStruct;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Exception;

class ConnectedServiceStruct extends AbstractDaoSilentStruct implements IDaoStruct {

    public ?int    $id                 = null;
    public int     $uid;
    public string  $service;
    public string  $email;
    public string  $name;
    public ?string $remote_id          = null;
    public ?string $oauth_access_token = null;
    public string  $created_at;
    public ?string $updated_at         = null;
    public ?string $expired_at         = null;
    public ?string $disabled_at        = null;
    public int     $is_default         = 1;

    /**
     * Returns the decoded access token.
     *
     * @return string|null
     * @throws EnvironmentIsBrokenException
     */
    public function getDecryptedOauthAccessToken(): ?string {
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