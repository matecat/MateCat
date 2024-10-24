<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 25/11/2016
 * Time: 13:19
 */

namespace Users\Authentication;

use API\Commons\Exceptions\ValidationError;
use Exception;
use Routes;
use Users_UserDao;
use Users_UserStruct;
use Utils;


class PasswordResetModel {

    protected ?string $token;
    /**
     * @var ?Users_UserStruct
     */
    protected ?Users_UserStruct $user = null;
    protected array             $session;

    /**
     * @param array       $session reference to global $_SESSSION var
     * @param string|null $token
     */
    public function __construct( array &$session, ?string $token = null ) {
        $this->token   = $token;
        $this->session =& $session;
        if ( empty( $token ) ) {
            $this->token = $session[ 'password_reset_token' ];
        }
    }

    /**
     * @return Users_UserStruct|null
     */
    public function getUser(): ?Users_UserStruct {
        return $this->user;
    }

    /**
     * Retrieves the user associated with the reset token.
     *
     * @return ?Users_UserStruct The user associated with the reset token, or null if not found.
     * @throws Exception If an error occurs while retrieving the user.
     *
     */
    protected function getUserFromResetToken(): ?Users_UserStruct {
        if ( !isset( $this->user ) ) {
            $dao        = new Users_UserDao();
            $this->user = $dao->getByConfirmationToken( $this->token );
        }

        return $this->user;
    }

    /**
     * Validates the user based on the reset token
     *
     * @throws ValidationError if confirmation token not found or auth token expired
     * @throws Exception if an error occurs
     */
    public function validateUser() {

        $this->getUserFromResetToken();

        if ( !$this->user ) {
            throw new ValidationError( 'Invalid authentication token' );
        }

        if ( strtotime( $this->user->confirmation_token_created_at ) < strtotime( '30 minutes ago' ) ) {
            $this->user->clearAuthToken();
            Users_UserDao::updateStruct( $this->user, [ 'fields' => [ 'confirmation_token' ] ] );

            throw new ValidationError( 'Auth token expired, repeat the operation.' );
        }

        $this->session[ 'password_reset_token' ] = $this->user->confirmation_token;

    }

    /**
     * @param string $new_password
     * @param string $password_confirmation
     *
     * @return void
     * @throws ValidationError
     * @throws Exception
     */
    public function resetPassword( string $new_password, string $password_confirmation ) {

        $this->getUserFromResetToken();

        if ( !$this->user ) {
            throw new ValidationError( 'Invalid authentication token' );
        }

        unset( $this->session[ 'password_reset_token' ] );

        UserPasswordValidator::validatePassword( $new_password, $password_confirmation );

        $this->user->pass = Utils::encryptPass( $new_password, $this->user->salt );

        // reset token
        $this->user->clearAuthToken();

        $fieldsToUpdate = [
                'fields' => [
                        'pass',
                        'confirmation_token',
                        'confirmation_token_created_at'
                ]
        ];

        // update email_confirmed_at only if it's null
        if ( null === $this->user->email_confirmed_at ) {
            $this->user->email_confirmed_at = date( 'Y-m-d H:i:s' );
            $fieldsToUpdate[ 'fields' ][]   = 'email_confirmed_at';
        }

        Users_UserDao::updateStruct( $this->user, $fieldsToUpdate );
        ( new Users_UserDao )->destroyCacheByEmail( $this->user->email );
        ( new Users_UserDao )->destroyCacheByUid( $this->user->uid );

    }

    /**
     * @return string
     * @throws Exception
     */
    public function flushWantedURL(): string {
        $url = $this->session[ 'wanted_url' ] ?? Routes::appRoot();
        unset( $this->session[ 'wanted_url' ] );

        return $url;
    }

}