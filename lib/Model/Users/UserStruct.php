<?php

use ConnectedServices\OauthTokenEncryption;
use DataAccess\AbstractDaoSilentStruct;
use DataAccess\IDaoStruct;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Teams\MembershipDao;
use Teams\TeamDao;
use Teams\TeamStruct;
use Users\MetadataDao;
use Users\MetadataStruct;

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 01/04/15
 * Time: 12.54
 */
class Users_UserStruct extends AbstractDaoSilentStruct implements IDaoStruct {

    public ?int    $uid                           = null;
    public ?string $email                         = null;
    public ?string $create_date                   = null;
    public ?string $first_name                    = null;
    public ?string $last_name                     = null;
    public ?string $salt                          = null;
    public ?string $pass                          = null;
    public ?string $oauth_access_token            = null;
    public ?string $email_confirmed_at            = null;
    public ?string $confirmation_token            = null;
    public ?string $confirmation_token_created_at = null;

    /**
     * @return bool
     */
    public function isAnonymous(): bool {
        return !$this->isLogged();
    }

    /**
     * @return bool
     */
    public function isLogged(): bool {
        return !empty( $this->uid ) &&
                !empty( $this->email ) &&
                !empty( $this->first_name ) &&
                !empty( $this->last_name );
    }

    public function clearAuthToken() {
        $this->confirmation_token            = null;
        $this->confirmation_token_created_at = null;
    }

    public function initAuthToken() {
        $this->confirmation_token            = Utils::randomString( 50, true );
        $this->confirmation_token_created_at = Utils::mysqlTimestamp( time() );
    }

    public static function getStruct(): Users_UserStruct {
        return new Users_UserStruct();
    }

    public function everSignedIn(): bool {
        return !( is_null( $this->email_confirmed_at ) && is_null( $this->oauth_access_token ) );
    }

    public function fullName(): string {
        return trim( $this->first_name . ' ' . $this->last_name );
    }

    public function shortName(): string {
        return trim( mb_substr( $this->first_name, 0, 1 ) . mb_substr( $this->last_name, 0, 1 ) );
    }

    public function getEmail(): ?string {
        return $this->email;
    }

    /**
     * @return ?int
     */
    public function getUid(): ?int {
        return $this->uid;
    }

    /**
     * @return string
     */
    public function getFirstName(): ?string {
        return $this->first_name;
    }

    /**
     * @return string|null
     */
    public function getLastName(): ?string {
        return $this->last_name;
    }

    /**
     * @return TeamStruct
     * @throws ReflectionException
     */
    public function getPersonalTeam(): TeamStruct {
        $oDao = new TeamDao();
        $oDao->setCacheTTL( 60 * 60 * 24 );

        return $oDao->getPersonalByUser( $this );
    }

    /**
     * @return TeamStruct[]|null
     * @throws ReflectionException
     */
    public function getUserTeams(): ?array {
        $mDao = new MembershipDao();
        $mDao->setCacheTTL( 60 * 60 * 24 );

        return $mDao->findUserTeams( $this );
    }

    /**
     * @return array
     */
    public function getMetadataAsKeyValue(): array {
        $dao        = new MetadataDao();
        $collection = $dao->getAllByUid( $this->uid );
        $data       = [];

        /** @var MetadataStruct $record */
        foreach ( $collection as $record ) {
            $data[ $record->key ] = $record->getValue();
        }

        $mandatory = [
                'dictation'              => 0,
                'show_whitespace'        => 0,
                'guess_tags'             => 1,
                'lexiqa'                 => 1,
                'character_counter'      => 0,
                'ai_assistant'           => 0,
                'cross_language_matches' => new stdClass(),
        ];

        foreach ( $mandatory as $key => $value ) {
            if ( !isset( $data[ $key ] ) ) {
                $data[ $key ] = is_numeric( $value ) ? (int)$value : $value;
            }
        }

        return $data;
    }

    /**
     * Returns true if password matches
     *
     * @param $password
     *
     * @return bool
     */
    public function passwordMatch( $password ): bool {
        return Utils::verifyPass( $password, $this->salt, $this->pass );
    }

    /**
     * Returns the decoded access token.
     *
     * @return null|string
     * @throws EnvironmentIsBrokenException
     */
    public function getDecryptedOauthAccessToken(): ?string {
        $oauthTokenEncryption = OauthTokenEncryption::getInstance();

        return $oauthTokenEncryption->decrypt( $this->oauth_access_token );
    }

    /**
     * @param null $field
     *
     * @return mixed
     * @throws Exception
     */
    public function getDecodedOauthAccessToken( $field = null ) {
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
