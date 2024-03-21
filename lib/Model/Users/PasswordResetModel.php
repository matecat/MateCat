<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 25/11/2016
 * Time: 13:19
 */

namespace Users;

use Exception;
use Exceptions\ValidationError;
use Users_UserDao;
use Users_UserStruct;
use Utils;


class PasswordResetModel {

    protected $token;
    /**
     * @var \Users_UserStruct
     */
    protected $user;
    protected $session;

    public function __construct( $token, $session ) {
        $this->token   = $token;
        $this->session = $session;
        if ( empty( $token ) ) {
            $this->token = $session[ 'password_reset_token' ];
        }
    }


    /**
     * Retrieves the user associated with the reset token.
     *
     * @return Users_UserStruct The user associated with the reset token, or null if not found.
     * @throws Exception If an error occurs while retrieving the user.
     *
     */
    protected function getUserFromResetToken() {
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
            throw new ValidationError( 'Confirmation token not found' );
        }

        if ( strtotime( $this->user->confirmation_token_created_at ) < strtotime( '30 minutes ago' ) ) {
            $this->user->clearAuthToken();
            Users_UserDao::updateStruct( $this->user, [ 'fields' => [ 'confirmation_token' ] ] );

            throw new ValidationError( 'Auth token expired, repeat the operation.' );
        }

        $_SESSION[ 'password_reset_token' ] = $this->user->confirmation_token;

    }

    /**
     * @throws ValidationError
     * @throws Exception
     */
    public function resetPassword( $new_password, $password_confirmation ) {

        $this->getUserFromResetToken();

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
}