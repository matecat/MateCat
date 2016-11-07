<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 01/04/15
 * Time: 12.54
 */


class Users_UserStruct extends DataAccess_AbstractDaoSilentStruct   implements DataAccess_IDaoStruct {

    public $uid;
    public $email;
    public $create_date;
    public $first_name;
    public $last_name;
    public $salt;
    public $api_key;
    public $pass;
    public $oauth_access_token ; 

    public static function getStruct() {
        return new Users_UserStruct();
    }

    public function fullName() {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function shortName() {
        return trim( mb_substr( $this->first_name, 0, 1 ) . "" . mb_substr( $this->last_name, 0, 1 ) );
    }

    public function getEmail() {
        return $this->email ;
    }

    /**
     * @return mixed
     */
    public function getUid() {
        return $this->uid;
    }

    /**
     * @return mixed
     */
    public function getFirstName() {
        return $this->first_name;
    }

    /**
     * @return mixed
     */
    public function getLastName() {
        return $this->last_name;
    }

    // TODO ------- start duplicated code, find a way to remove duplication

    /**
     * Returns the decoded access token.
     *
     * @param null $field
     *
     */
    public function getDecryptedOauthAccessToken() {
        return $this->cachable('decrypted', $this, function($object) {
            $oauthTokenEncryption = OauthTokenEncryption::getInstance();
            return $oauthTokenEncryption->decrypt( $object->oauth_access_token );
        });
    }

    /**
     * @param null $field
     * @return mixed
     * @throws \Exception
     */
    public function getDecodedOauthAccessToken($field=null) {
        $decoded = json_decode( $this->getDecryptedOauthAccessToken(), TRUE );

        if ( $field ) {
            if ( array_key_exists( $field, $decoded ) ) {
                return $decoded[ $field ] ;
            }
            else {
                throw new \Exception('key not found on token: ' . $field ) ;
            }
        }

        return $decoded  ;
    }


}
