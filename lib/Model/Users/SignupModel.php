<?php

namespace Users;

use AuthCookie;
use Database;
use Email\ForgotPasswordEmail;
use Email\SignupEmail;
use Email\WelcomeEmail;
use Exceptions\ValidationError;
use Teams\TeamDao;
use Users_UserDao;
use Users_UserStruct;
use Utils;

class SignupModel {

    /**
     * @var Users_UserStruct
     */
    protected $user;

    protected $params;

    protected $error;

    protected $mailer;

    public function __construct( $params ) {
        $this->params = filter_var_array( $params, [
                'email'                 => FILTER_SANITIZE_EMAIL,
                'password'              => [ 'filter' => FILTER_SANITIZE_STRING, 'options' => FILTER_FLAG_STRIP_LOW ],
                'password_confirmation' => [ 'filter' => FILTER_SANITIZE_STRING, 'options' => FILTER_FLAG_STRIP_LOW ],
                'first_name'            => [
                        'filter' => FILTER_CALLBACK, 'options' => function ( $username ) {
                            return mb_substr( preg_replace( '/(?:https?|s?ftp)?\P{L}+/', '', $username ), 0, 50 );
                        }
                ],
                'last_name'             => [
                        'filter' => FILTER_CALLBACK, 'options' => function ( $username ) {
                            return mb_substr( preg_replace( '/(?:https?|s?ftp)?\P{L}+/', '', $username ), 0, 50 );
                        }
                ],
                'wanted_url'            => FILTER_SANITIZE_URL
        ] );

        $this->user = new Users_UserStruct( $this->params );
    }


    /**
     * @return Users_UserStruct
     */
    public function getUser() {
        return $this->user;
    }

    public function valid() {
        try {
            $this->__doValidation();
        } catch ( ValidationError $e ) {
            $this->error = $e->getMessage();

            return false;
        }

        return true;
    }

    /**
     * @throws ValidationError
     */
    public function process() {
        $this->error = null;

        $this->__doValidation();

        if ( $this->__userAlreadyExists() ) {
            $this->__updatePersistedUser();
            Users_UserDao::updateStruct( $this->user, [ 'raise' => true ] );
        } else {
            $this->__prepareNewUser();
            $this->user->uid = Users_UserDao::insertStruct( $this->user, [ 'raise' => true ] );

            Database::obtain()->begin();
            ( new TeamDao() )->createPersonalTeam( $this->user );
            Database::obtain()->commit();
        }

        $this->__saveWantedUrl();

        // send confirmation email only if
        // user is not active
        if ( !$this->__userAlreadyExistsAndIsActive() ) {
            $this->__sendConfirmationRequestEmail();
        }
    }

    public function getError() {
        return $this->error;
    }

    private function __sendConfirmationRequestEmail() {
        $email = new SignupEmail( $this->getUser() );
        $email->send();
    }

    private function __saveWantedUrl() {
        $_SESSION[ 'wanted_url' ] = $this->params[ 'wanted_url' ];
    }

    private function __updatePersistedUser() {
        $this->user->pass = Utils::encryptPass( $this->params[ 'password' ], $this->user->salt );

        $this->user->confirmation_token            = Utils::randomString( 50, true );
        $this->user->confirmation_token_created_at = Utils::mysqlTimestamp( time() );
    }

    private function __prepareNewUser() {
        $this->user->create_date = Utils::mysqlTimestamp( time() );
        $this->user->salt        = Utils::randomString( 15, true );
        $this->user->pass        = Utils::encryptPass( $this->params[ 'password' ], $this->user->salt );

        $this->user->confirmation_token            = Utils::randomString( 50, true );
        $this->user->confirmation_token_created_at = Utils::mysqlTimestamp( time() );
    }

    /**
     * Check if a user already exists
     *
     * @return bool
     */
    private function __userAlreadyExists() {

        $dao       = new Users_UserDao();
        $persisted = $dao->getByEmail( $this->user->email );

        if ( $persisted ) {
            $this->user = $persisted;
        }

        return isset( $this->user->uid );
    }

    /**
     * Check if a user already exists AND is active
     *
     * @return bool
     */
    private function __userAlreadyExistsAndIsActive() {
        return ( isset( $this->user->uid ) and null !== $this->user->email_confirmed_at );
    }


    /**
     * @throws ValidationError
     */
    private function __doValidation() {

        UserPasswordValidator::validatePassword( $this->params[ 'password' ], $this->params[ 'password_confirmation' ] );

        if ( empty( $this->params[ 'first_name' ] ) ) {
            throw new ValidationError( 'First name must be set' );
        }

        if ( empty( $this->params[ 'last_name' ] ) ) {
            throw new ValidationError( 'Last name must be set' );
        }

    }

    /**
     * @throws ValidationError
     */
    public static function confirm( $token ) {
        $dao  = new Users_UserDao();
        $user = $dao->getByConfirmationToken( $token );

        if ( !$user ) {
            throw new ValidationError( 'Confirmation token not found' );
        }

        if ( strtotime( $user->confirmation_token_created_at ) < strtotime( '3 days ago' ) ) {
            throw new ValidationError( 'Confirmation token is too old, please contact support.' );
        }

        $ever_signed_in = $user->everSignedIn();

        $user = self::__updateUserFields( $user );

        if ( !$ever_signed_in ) {
            $email = new WelcomeEmail( $user );
            $email->send();
        }

        AuthCookie::setCredentials( $user->email, $user->uid );

        return $user;

    }

    /**
     * @param $email
     * @param $wanted_url
     *
     * @return bool
     * @throws \Exception
     */
    public static function forgotPassword( $email, $wanted_url ) {

        $email                    = filter_var( $email, FILTER_SANITIZE_EMAIL );
        $wanted_url               = filter_var( $wanted_url, FILTER_SANITIZE_URL );
        $_SESSION[ 'wanted_url' ] = $wanted_url;

        $dao  = new Users_UserDao();
        $user = $dao->getByEmail( $email );

        if ( $user ) {
            $user->initAuthToken();

            Users_UserDao::updateStruct( $user, [ 'fields' => [ 'confirmation_token', 'confirmation_token_created_at' ] ] );

            $delivery = new ForgotPasswordEmail( $user );
            $delivery->send();

            return true;
        }

        return false;

    }

    public static function resendEmailConfirm( $email ) {
        $email = filter_var( $email, FILTER_SANITIZE_EMAIL );

        $dao  = new Users_UserDao();
        $user = $dao->getByEmail( $email );

        if ( $user ) {
            $delivery = new SignupEmail( $user );
            $delivery->send();
        }

    }

    /**
     * @param Users_UserStruct $user
     *
     * @return Users_UserStruct
     * @throws ValidationError
     */
    private static function __updateUserFields( Users_UserStruct $user ) {
        $user->email_confirmed_at = Utils::mysqlTimestamp( time() );
        $user->confirmation_token = null;

        Users_UserDao::updateStruct( $user, [ 'fields' => [ 'confirmation_token', 'email_confirmed_at' ] ] );
        ( new Users_UserDao )->destroyCacheByEmail( $user->email );
        ( new Users_UserDao )->destroyCacheByUid( $user->uid );

        return $user;
    }

}