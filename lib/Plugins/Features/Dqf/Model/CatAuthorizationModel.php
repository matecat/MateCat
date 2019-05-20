<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 10/07/2017
 * Time: 11:18
 */

namespace Features\Dqf\Model;

use Jobs\MetadataDao;
use Jobs_JobStruct;
use Users_UserDao;
use Users_UserStruct;

class CatAuthorizationModel {

    const DQF_TRANSLATE_USER = 'dqf_translate_user' ;
    const DQF_REVISE_USER    = 'dqf_revise_user';

    const STATUS_NOT_ASSIGNED             = 'not_assigned' ;
    const STATUS_USER_NOT_MATCHING        = 'not_matching' ;
    const STATUS_USER_INVALID_CREDENTIALS = 'invalid_credentials' ;
    const STATUS_USER_ANONYMOUS           = 'anonymous' ;

    /**
     * @var Jobs_JobStruct
     */
    protected $job;

    /**
     * @var MetadataDao
     */
    protected  $dao ;

    public function __construct( Jobs_JobStruct $job, $isReview ) {
        $this->job = $job ;
        $this->key = ( $isReview ? self::DQF_REVISE_USER : self::DQF_TRANSLATE_USER ) ;
        $this->dao = new MetadataDao() ;
    }

    public function revokeAssignment() {
        $this->dao->delete( $this->job->id, $this->job->password, $this->key );
    }

    public function assignJobToUser( Users_UserStruct $user ) {
        $status = $this->getStatus( $user );

        if ( $status == self::STATUS_NOT_ASSIGNED ) {
            $insertDone = $this->setAuthorizedUser( $user ) ;

            if ( $insertDone ) {
                return true;
            }
        }
        return false ;
    }

    public function getStatus( Users_UserStruct $user ) {

        if ( $user->isAnonymous() ) {
            return self::STATUS_USER_ANONYMOUS ;
        }

        $uid = $this->getAuthorizedUid()  ;
        if ( ! $uid ) {
            return self::STATUS_NOT_ASSIGNED ;
        }
        elseif ( $uid != $user->uid ) {
            return self::STATUS_USER_NOT_MATCHING ;
        }
        else {
            $storedUser = ( new Users_UserDao())->getByUid( $uid ) ;
            $dqfCredentialsStatus = $this->dqfUserCredentialsInvalidStatus( $storedUser );
            if ( is_null( $dqfCredentialsStatus ) ) {
                return $storedUser->uid ;
            }
        }
    }

    public function isUserAuthorized( Users_UserStruct $user ) {
        return $this->getAuthorizedUid() == $user->uid && !is_null( $user->uid ) ;
    }

    protected function dqfUserCredentialsInvalidStatus( Users_UserStruct $user ) {
        $dqfUser = new UserModel( $user );

        if ( !$dqfUser->validCredentials() ) {
            return self::STATUS_USER_INVALID_CREDENTIALS ;
        }

        return null;
    }

    protected function setAuthorizedUser( Users_UserStruct $user ) {
        return $this->dao->set( $this->job->id, $this->job->password, $this->key, $user->uid );
    }

    public function getAuthorizedUid() {
        $record = $this->dao->get( $this->job->id, $this->job->password, $this->key );
        if ( !$record ) {
            return false ;
        }
        return $record->value ;
    }

    /**
     * @return bool|Users_UserStruct
     */
    public function getAuthorizedUser() {
        $uid = $this->getAuthorizedUid();
        if ( $uid ) {
            return ( new Users_UserDao() )->getByUid( $uid ) ;
        } else {
            return false ;
        }
    }

}