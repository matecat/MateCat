<?php

namespace Model\Users\Authentication;

use Controller\API\Commons\Exceptions\ValidationError;
use Email\ForgotPasswordEmail;
use Email\SignupEmail;
use Email\WelcomeEmail;
use Exception;
use Model\Database;
use Model\Teams\TeamDao;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use ReflectionException;
use Routes;
use Utils;

class SignupModel {

    /**
     * @var UserStruct
     */
    protected UserStruct $user;

    protected array $params;

    protected ?string $error = null;
    private array     $session;

    public function __construct( array $params, array &$session ) {
        $this->params  = $params;
        $this->session =& $session;
        $this->user    = new UserStruct( $this->params );
    }

    /**
     * @return array
     */
    public function getParams(): array {
        return $this->params;
    }

    /**
     * @return \Model\Users\UserStruct
     */
    public function getUser(): UserStruct {
        return $this->user;
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function processSignup() {

        if ( $this->__userAlreadyExists() ) {
            $this->__updatePersistedUser();
            UserDao::updateStruct( $this->user, [
                    'fields' => [
                            'salt',
                            'pass',
                            'confirmation_token',
                            'confirmation_token_created_at'
                    ]
            ] );
        } else {
            $this->__prepareNewUser();
            $this->user->uid = UserDao::insertStruct( $this->user );

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

    /**
     * @throws Exception
     */
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
     * @throws ReflectionException
     */
    private function __userAlreadyExists(): bool {

        $dao       = new UserDao();
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
     * @throws Exception
     */
    public function confirm(): UserStruct {
        $dao  = new UserDao();
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

        $dao  = new UserDao();
        $user = $dao->getByEmail( $this->params[ 'email' ] );

        if ( $user ) {
            $user->initAuthToken();

            UserDao::updateStruct( $user, [ 'fields' => [ 'confirmation_token', 'confirmation_token_created_at' ] ] );

            $delivery = new ForgotPasswordEmail( $user );
            $delivery->send();

            return true;
        }

        return false;

    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public static function resendConfirmationEmail( $email ) {
        $email = filter_var( $email, FILTER_SANITIZE_EMAIL );

        $dao  = new UserDao();
        $user = $dao->getByEmail( $email );

        if ( $user ) {
            $delivery = new SignupEmail( $user );
            $delivery->send();
        }

    }

    /**
     * @param UserStruct $user
     *
     * @return UserStruct
     * @throws Exception
     */
    private static function __updateUserFields( UserStruct $user ): UserStruct {

        $user->email_confirmed_at = Utils::mysqlTimestamp( time() );
        $user->clearAuthToken();

        UserDao::updateStruct( $user, [ 'fields' => [ 'confirmation_token', 'email_confirmed_at' ] ] );
        ( new UserDao )->destroyCacheByEmail( $user->email );
        ( new UserDao )->destroyCacheByUid( $user->uid );

        return $user;
    }

}