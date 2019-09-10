<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 25/11/2016
 * Time: 13:19
 */

namespace Users;

use AuthCookie;
use Exceptions\ValidationError;
use Users_UserDao;


class PasswordReset {

    protected $token;
    /**
     * @var \Users_UserStruct
     */
    protected $user;
    protected $session;

    public function __construct( $token, $session ) {
        $this->token   = $token;
        $this->session = $session;
    }


    public function getUser() {
        if ( !isset( $this->user ) ) {
            $dao        = new \Users_UserDao();
            $this->user = $dao->getByConfirmationToken( $this->token );
        }

        return $this->user;
    }

    public function authenticateUser() {
        $this->getUser();

        if ( !$this->user ) {
            throw new ValidationError( 'Confirmation token not found' );
        }

        if ( strtotime( $this->user->confirmation_token_created_at ) < strtotime( '3 days ago' ) ) {
            $this->user->clearAuthToken();
            Users_UserDao::updateStruct( $this->user, [ 'fields' => [ 'confirmation_token' ] ] );

            throw new ValidationError( 'Auth token expired, repeat the operation.' );
        }

        $this->user->clearAuthToken();

        Users_UserDao::updateStruct( $this->user, [ 'fields' => [ 'confirmation_token', 'confirmation_token_created_at' ] ] );

        AuthCookie::setCredentials( $this->user->email, $this->user->uid );

    }
}