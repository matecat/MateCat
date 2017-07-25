<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 10/07/2017
 * Time: 13:01
 */

namespace Features\Dqf\Model;

use API\V2\Exceptions\AuthenticationError;
use Exception;
use Features\Dqf\Service\Session;
use Users_UserDao;
use Users_UserStruct;

class UserModel {

    protected $user ;
    protected $metadata ;

    protected $session ;

    public function __construct( Users_UserStruct $user ) {

        if ( is_null( $user ) ) {
            throw  new Exception('User is null') ;
        }

        $this->user = $user ;
        $this->metadata = $this->user->getMetadataAsKeyValue();
    }

    public function getSession() {
        if ( ! isset( $this->session ) ) {
            if ( ! $this->hasCredentials() ) {
                throw  new Exception('Credentials are not set' );
            }
            $this->session = new Session( $this->metadata['dqf_username'], $this->metadata['dqf_password'] ) ;
        }
        return $this->session ;
    }

    public function getDqfUsername() {
        return $this->metadata['dqf_username'];
    }

    /**
     * @return bool
     */
    public function hasCredentials() {
        return ( isset( $this->metadata['dqf_username'] ) && isset( $this->metadata['dqf_password'] ) ) ;
    }

    /**
     * @return bool
     */
    public function validCredentials() {
        try {
            $this->getSession()->login() ;
        } catch( AuthenticationError $e ) {
            return false ;
        }
        return true ;
    }

    public static function createByEmail( $email ) {
        $user = ( new Users_UserDao() )->getByEmail( $email ) ;
        return new UserModel( $user );
    }
}
