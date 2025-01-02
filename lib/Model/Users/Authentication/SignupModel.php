<?php

namespace Users\Authentication;

use API\Commons\Exceptions\ValidationError;
use Database;
use Email\ForgotPasswordEmail;
use Email\SignupEmail;
use Email\WelcomeEmail;
use Exception;
use Routes;
use Teams\TeamDao;
use Users_UserDao;
use Users_UserStruct;
use Utils;

class SignupModel {

    /**
     * @var Users_UserStruct
     */
    protected Users_UserStruct $user;

    protected array $params;

    protected ?string $error = null;
    private array     $session;

    public function __construct( array $params, array &$session ) {
        $this->params  = $params;
        $this->session =& $session;
        $this->user    = new Users_UserStruct( $this->params );
    }

    /**
     * @return array
     */
    public function getParams(): array {
        return $this->params;
    }

    /**
     * @return Users_UserStruct
     */
    public function getUser(): Users_UserStruct {
        return $this->user;
    }

    /**
     * @return bool
     */
    public function valid(): bool {
        try {
            $this->__doValidation();
        } catch ( ValidationError $e ) {
            $this->error = $e->getMessage();

            return false;
        }

        return true;
    }

    /**
     * @throws \ReflectionException
     */
    public function processSignup() {

        if ( $this->__userAlreadyExists() ) {
            $this->__updatePersistedUser();
            Users_UserDao::updateStruct( $this->user, [
                    'fields' => [
                            'salt',
                            'pass',
                            'confirmation_token',
                            'confirmation_token_created_at'
                    ]
            ] );
        } else {
            $this->__prepareNewUser();
            $this->user->uid = Users_UserDao::insertStruct( $this->user );

            Database::obtain()->begin();
            ( new TeamDao() )->createPersonalTeam( $this->user );
            Database::obtain()->commit();
        }

        $this->__saveWantedUrl();

        // send a confirmation email only if
        // the user is not active (with a user/password pair)
        // AND do not own an active Oauth login
        if ( !$this->__userAlreadyExistsAndIsActive() ) {
            $this->__sendConfirmationRequestEmail();
        }

    }

    /**
     * @return string|null
     */
    public function getError(): ?string {
        return $this->error;
    }

    private function __sendConfirmationRequestEmail() {
        $email = new SignupEmail( $this->getUser() );
        $email->send();
    }

    private function __saveWantedUrl() {
        $this->session[ 'wanted_url' ] = $this->params[ 'wanted_url' ];
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

    private function __updatePersistedUser() {

        /*
         * salt is empty when a user exists, and it's first login happened through external service providers (OAuth)
         * Check the salt before join the two accounts.
         */
        if ( empty( $this->user->salt ) ) {
            $this->user->salt = Utils::randomString( 15, true );
        }

        $this->user->pass = Utils::encryptPass( $this->params[ 'password' ], $this->user->salt );

        $this->user->initAuthToken();
    }

    private function __prepareNewUser() {

        $this->user->create_date = Utils::mysqlTimestamp( time() );
        $this->user->salt        = Utils::randomString( 15, true );
        $this->user->pass        = Utils::encryptPass( $this->params[ 'password' ], $this->user->salt );

        $this->user->initAuthToken();

    }

    /**
     * Check if a user already exists
     *
     * @return bool
     */
    private function __userAlreadyExists(): bool {

        $dao       = new Users_UserDao();
        $persisted = $dao->getByEmail( $this->user->email );

        if ( $persisted ) {
            $this->user = $persisted;
        }

        return isset( $this->user->uid );
    }

    /**
     * Check if a user already exists
     * AND
     * is active (with a user/password pair)
     * OR do not own an active Oauth login
     *
     *
     * @return bool
     */
    private function __userAlreadyExistsAndIsActive(): bool {
        return ( isset( $this->user->uid ) && ( !empty( $this->user->email_confirmed_at ) || !empty( $this->user->oauth_access_token ) ) );
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
     * @throws Exception
     */
    public function confirm(): Users_UserStruct {
        $dao  = new Users_UserDao();
        $user = $dao->getByConfirmationToken( $this->params[ 'token' ] );

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

        return $user;

    }

    /**
     * @return bool
     * @throws Exception
     */
    public function forgotPassword(): bool {

        $this->__saveWantedUrl();

        $dao  = new Users_UserDao();
        $user = $dao->getByEmail( $this->params[ 'email' ] );

        if ( $user ) {
            $user->initAuthToken();

            Users_UserDao::updateStruct( $user, [ 'fields' => [ 'confirmation_token', 'confirmation_token_created_at' ] ] );

            $delivery = new ForgotPasswordEmail( $user );
            $delivery->send();

            return true;
        }

        return false;

    }

    public static function resendConfirmationEmail( $email ) {
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
     * @throws Exception
     */
    private static function __updateUserFields( Users_UserStruct $user ): Users_UserStruct {

        $user->email_confirmed_at = Utils::mysqlTimestamp( time() );
        $user->clearAuthToken();

        Users_UserDao::updateStruct( $user, [ 'fields' => [ 'confirmation_token', 'email_confirmed_at' ] ] );
        ( new Users_UserDao )->destroyCacheByEmail( $user->email );
        ( new Users_UserDao )->destroyCacheByUid( $user->uid );

        return $user;
    }

}