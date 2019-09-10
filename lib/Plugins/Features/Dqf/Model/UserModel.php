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
use Features\Dqf\Service\GenericSession;
use Features\Dqf\Service\ISession;
use Features\Dqf\Service\Session;
use Users_UserStruct;

class UserModel {

    protected $user ;
    protected $metadata ;

    /**
     * @var ISession
     */
    protected $session ;

    public function __construct( Users_UserStruct $user ) {

        if ( is_null( $user ) ) {
            throw  new Exception('User is null') ;
        }

        $this->user = $user ;
        $this->metadata = $this->user->getMetadataAsKeyValue();
    }

    /**
     * @return GenericSession|ISession|Session
     */
    public function getSession() {
        if ( ! isset( $this->session ) ) {
            if ( $this->hasCredentials() ) {
                $this->session = new Session( $this->metadata['dqf_username'], $this->metadata['dqf_password'] ) ;
            }
            else {
                $this->session = new GenericSession( $this->user->email ) ;
            }
        }
        return $this->session ;
    }

    public function getDqfUsernameOrMateCatEmail() {
        return is_null( $this->metadata['dqf_username'] ) ?
                $this->getMateCatUser()->email :
                $this->metadata['dqf_username'] ;
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

    /**
     * @return Users_UserStruct
     */
    public function getMateCatUser() {
        return $this->user ;
    }
}
