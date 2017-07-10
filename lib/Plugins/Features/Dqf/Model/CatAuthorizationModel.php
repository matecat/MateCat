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
    const STATUS_USER_NO_CREDENTIALS      = 'no_credentials'  ;
    const STATUS_USER_INVALID_CREDENTIALS = 'invalid_credentials' ;

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

    public function getStatusWithImplicitAssignment( Users_UserStruct $user ) {
        $status = $this->getStatus( $user );
        if ( $status == self::STATUS_NOT_ASSIGNED ) {
            $invalidCredentialsStatus = $this->dqfUserCredentialsInvalidStatus( $user );
            if ( is_null( $invalidCredentialsStatus ) ) {
                $insertDone = $this->dao->set( $this->job->id, $this->job->password, $this->key, $user->uid );
                if ( $insertDone ) {
                    $status = $user->uid ;
                }
            }
        }
        return $status ;
    }

    public function getStatus( Users_UserStruct $user ) {
        $record = $this->dao->get($this->job->id, $this->job->password, $this->key) ;

        if ( ! $record ) {
            return self::STATUS_NOT_ASSIGNED ;
        }
        elseif ( $record->value != $user->uid ) {
            return self::STATUS_USER_NOT_MATCHING ;
        }
        else {
            $storedUser = ( new Users_UserDao())->getByUid( $record->value ) ;
            $dqfCredentialsStatus = $this->dqfUserCredentialsInvalidStatus( $storedUser );
            if ( is_null( $dqfCredentialsStatus ) ) {
                return $storedUser->uid ;
            }
        }
    }

    public function isAuthorized( Users_UserStruct $user ) {
        $status = $this->getStatus($user) ;
    }

    protected function dqfUserCredentialsInvalidStatus( $user ) {
        $dqfUser = new UserModel( $user );

        if (!$dqfUser->hasCredentials() ) {
            return self::STATUS_USER_NO_CREDENTIALS ;
        }

        if ( !$dqfUser->validCredentials() ) {
            return self::STATUS_USER_INVALID_CREDENTIALS ;
        }

        return null;
    }

    protected function setAuthorizedUser( Users_UserStruct $user ) {
        $this->dao->set($this->job->id, $this->job->password, $this->key, $user->uid );
    }

}