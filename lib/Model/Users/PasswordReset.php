<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 25/11/2016
 * Time: 13:19
 */

namespace Users;

use Exceptions\ValidationError ;
use Users_UserDao ;

use Utils;

use AuthCookie ;


class PasswordReset
{

    protected $token;
    /**
     * @var \Users_UserStruct
     */
    protected $user ;
    protected $session ;

    public function __construct($token, $session)
    {
        $this->token = $token ;
        $this->session = $session ;
    }


    public function getUser() {
        return $this->user ;
    }

    public function authenticateUser() {
        $dao = new \Users_UserDao() ;
        $user = $dao->getByConfirmationToken( $this->token );

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

        AuthCookie::setCredentials($this->getUser()->email, $this->getUser()->uid);

    }
}