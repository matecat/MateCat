<?php

namespace Users ;

use Email\ForgotPasswordEmail;
use Email\SignupEmail;
use Exceptions\ValidationError;
use Users_UserStruct ;
use Utils ;
use Users_UserDao;
use AuthCookie ;

class Signup {

    /**
     * Users_UserStruct
     */
    protected $user ;

    protected $params ;

    protected $error ;

    protected $mailer ;

    public function __construct( $params ) {
        $this->params = filter_var_array( $params, array(
            'email' => FILTER_SANITIZE_EMAIL,
            'password' => FILTER_SANITIZE_STRING,
            'password_confirmation' => FILTER_SANITIZE_STRING,
            'first_name' => FILTER_SANITIZE_STRING,
            'last_name' => FILTER_SANITIZE_STRING,
            'wanted_url' => FILTER_SANITIZE_URL
        ));

        $this->user = new Users_UserStruct( $params );
    }


    /**
     * @return Users_UserStruct
     */
    public function getUser() {
        return $this->user ;
    }

    public function valid() {
        try {
            $this->__doValidation()  ;
        } catch( \Exceptions\ValidationError $e ) {
            $this->error = $e->getMessage() ;
            return false;
        }
        return true ;
    }

    public function process() {
        $this->error = null ;

        $this->__doValidation() ;

        $this->__prepareUserRecord() ;

        \Users_UserDao::insertStruct( $this->user, array('raise' => TRUE ) );

        $this->__saveWantedUrl();

        $this->__sendConfirmationRequestEmail();

    }

    public function getError() {
        return $this->error ;
    }

    private function __sendConfirmationRequestEmail() {
        $email = new SignupEmail( $this->getUser() ) ;
        $email->send();
    }

    private function __saveWantedUrl() {
        \Bootstrap::sessionStart();
        $_SESSION['wanted_url'] = $this->params['wanted_url'] ;
    }

    private function __prepareUserRecord() {
        $this->user->create_date = Utils::mysqlTimestamp( time() );
        $this->user->salt = Utils::randomString() ;
        $this->user->pass = Utils::encryptPass( $this->params['password'], $this->user->salt ) ;

        $this->user->confirmation_token = Utils::randomString() ;
        $this->user->confirmation_token_created_at = Utils::mysqlTimestamp( time() );
    }


    private function __doValidation() {
        $dao = new \Users_UserDao() ;
        $exists = $dao->getByEmail( $this->user->email );

        // TODO: handle case in which the user exists but confirmation token expired
        if ( $exists ) {
            throw new \Exceptions\ValidationError('User with same email already exists');
        }

        \Users_UserValidator::validatePassword( $this->params['password'] ) ;

        if ( empty( $this->params['first_name'] ) ) {
            throw new \Exceptions\ValidationError('First name must be set') ;
        }

        if ( empty( $this->params['last_name'] ) ) {
            throw new \Exceptions\ValidationError('Last name must be set') ;
        }

    }

    public static function confirm( $token ) {
        $dao = new \Users_UserDao() ;
        $user = $dao->getByConfirmationToken( $token );

        if ( !$user ) {
            throw new ValidationError('Confirmation token not found');
        }

        if ( strtotime( $user->confirmation_token_created_at ) < strtotime('3 days ago') ) {
            throw new ValidationError('Confirmation token is too old, please contact support.') ;
        }

        $user->email_confirmed_at = Utils::mysqlTimestamp( time() ) ;
        $user->confirmation_token = null ;

        Users_UserDao::updateStruct( $user, array('fields' => array( 'confirmation_token', 'email_confirmed_at' ) ) ) ;

        AuthCookie::setCredentials($user->email, $user->uid);
        Utils::tryToRedeemProject( $user->email ) ;
    }

    public static function passwordReset( $token ) {
        $dao = new \Users_UserDao() ;
        $user = $dao->getByConfirmationToken( $token );

        if ( !$user ) {
            throw new ValidationError('Confirmation token not found');
        }

        if ( strtotime( $user->confirmation_token_created_at ) < strtotime('3 days ago') ) {
            $user->clearAuthToken();
            Users_UserDao::updateStruct( $user, array('fields' => array('confirmation_token')  ) ) ;

            throw new ValidationError('Auth token expired, repeat the operation.') ;
        }

        $user->clearAuthToken() ;

        Users_UserDao::updateStruct( $user, array('fields' => array('confirmation_token', 'confirmation_token_created_at')  ) ) ;
        AuthCookie::setCredentials($user->email, $user->uid);
    }

    public static function forgotPassword( $email ) {
        $email = filter_var( $email, FILTER_SANITIZE_EMAIL ) ;

        $dao = new Users_UserDao();
        $user = $dao->getByEmail( $email );

        if ( $user ) {
            $user->initAuthToken() ;

            Users_UserDao::updateStruct($user, array('fields' => array('confirmation_token', 'confirmation_token_created_at') ) );

            $delivery = new ForgotPasswordEmail( $user );
            $delivery->send();
        }

    }

    public static function resendEmailConfirm( $email ) {
        $email = filter_var( $email, FILTER_SANITIZE_EMAIL ) ;

        $dao = new Users_UserDao();
        $user = $dao->getByEmail( $email );

        if ( $user ) {
            $delivery = new SignupEmail( $user );
            $delivery->send();
        }

    }

}