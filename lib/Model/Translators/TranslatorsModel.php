<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 14/04/17
 * Time: 17.08
 *
 */

namespace Translators;


use API\V2\KleinController;
use Email\SendToTranslatorForDeliveryChangeEmail;
use Email\SendToTranslatorForJobSplitEmail;
use Email\SendToTranslatorForNewJobEmail;
use Exception;
use FeatureSet;
use InvalidArgumentException;
use Jobs_JobDao;
use Jobs_JobStruct;
use Outsource\ConfirmationDao;
use Projects_ProjectDao;
use Projects_ProjectStruct;
use TransactionableTrait;
use Users_UserDao;
use Users_UserStruct;
use Utils;

class TranslatorsModel {

    use TransactionableTrait;

    /**
     * @var Users_UserStruct
     */
    protected $callingUser;

    /**
     * @var Jobs_JobStruct
     */
    protected $jStruct;

    /**
     * @var KleinController
     */
    protected $controller;

    /**
     * @var array
     */
    protected $mailsToBeSent = [ 'new' => null, 'update' => null, 'split' => null ];

    protected $delivery_date;
    protected $job_owner_timezone = 0;
    protected $id_job;
    protected $email;
    protected $job_password;

    /**
     * @var Projects_ProjectStruct
     */
    protected $project;

    /**
     * @var FeatureSet
     */
    protected $featureSet;

    /**
     * Override the Job Password from Outside
     *
     * @param mixed $job_password
     *
     * @return $this
     */
    public function setNewJobPassword( $job_password ) {
        $this->job_password = $job_password;

        return $this;
    }

    /**
     * @var JobsTranslatorsStruct
     */
    protected $jobTranslator;

    /**
     * @param mixed $delivery_date
     *
     * @return $this
     */
    public function setDeliveryDate( $delivery_date ) {

        if ( is_numeric( $delivery_date ) && (int)$delivery_date == $delivery_date ) {
            $this->delivery_date = $delivery_date;
        } else {
            $this->delivery_date = strtotime( $delivery_date );
        }

        return $this;
    }

    /**
     * @param int $job_owner_timezone
     *
     * @return $this
     */
    public function setJobOwnerTimezone( $job_owner_timezone ) {
        $this->job_owner_timezone = $job_owner_timezone;
        return $this;
    }

    /**
     * @param mixed $email
     *
     * @return $this
     */
    public function setEmail( $email ) {
        $this->email = $email;

        return $this;
    }

    public function setUserInvite( Users_UserStruct $user ) {
        $this->callingUser = $user;

        return $this;
    }

    /**
     * TranslatorsModel constructor.
     *
     * @param Jobs_JobStruct $jStruct
     * @param float|int      $project_cache_TTL
     */
    public function __construct( Jobs_JobStruct $jStruct, $project_cache_TTL = 60 * 60 ) {

        //get the job
        $this->jStruct = $jStruct;

        $this->id_job       = $jStruct->id;
        $this->job_password = $jStruct->password;

        $this->project = $this->jStruct->getProject( $project_cache_TTL );
        $this->featureSet = $this->project->getFeaturesSet();

    }

    public function getTranslator( $cache = 86400 ) {

        $jTranslatorsDao    = new JobsTranslatorsDao();

        return $this->jobTranslator = @$jTranslatorsDao->setCacheTTL( $cache )->findByJobsStruct( $this->jStruct )[ 0 ];

    }

    /**
     * @return JobsTranslatorsStruct
     * @throws Exception
     */
    public function update() {

        $confDao            = new ConfirmationDao();
        $confirmationStruct = $confDao->getConfirmation( $this->jStruct );

        if ( !empty( $confirmationStruct ) ) {
            throw new InvalidArgumentException( "The Job is Outsourced.", 400 );
        }

        //create jobs_translator struct to call inside the dao
        $translatorStruct = new JobsTranslatorsStruct();

        $translatorUser = ( new Users_UserDao() )->setCacheTTL( 60 * 60 )->getByEmail( $this->email );
        if ( !empty( $translatorUser ) ) {
            //associate the translator with an existent user and create a profile if not exists
            $translatorStruct->id_translator_profile = $this->saveProfile( $translatorUser );
        }

        $jTranslatorsDao = new JobsTranslatorsDao();
        if ( empty( $this->jobTranslator ) ) { // self::getTranslator() can be called from outside
            // retrieve with no cache
            $this->getTranslator( 0 );
        }

        if ( !empty( $this->jobTranslator ) ) { // an associated translator already exists for this chunk

            if ( $this->jobTranslator->email != $this->email ) {

                //if the translator email changed ( differs from the existing one ), change the Job Password and insert a new row
                $this->changeJobPassword();

                //send a mail to the new translator
                $this->mailsToBeSent[ 'new' ] = $this->email;

            } elseif ( strtotime( $this->jobTranslator->delivery_date ) != $this->delivery_date ) {

                //send a mail to the translator if delivery_date changes
                $this->mailsToBeSent[ 'update' ] = $this->email;

            } elseif ( $this->jobTranslator->job_password != $this->job_password ) {

                $this->changeJobPassword( $this->job_password );

                $this->mailsToBeSent[ 'split' ] = $this->email;

            }

        } else {

            //send a mail to the new translator
            $this->mailsToBeSent[ 'new' ] = $this->email;

        }

        //set the old id and password to make "ON DUPLICATE KEY UPDATE" possible
        $translatorStruct->id_job             = $this->jStruct->id;
        $translatorStruct->job_password       = $this->jStruct->password;
        $translatorStruct->delivery_date      = Utils::mysqlTimestamp( $this->delivery_date );
        $translatorStruct->job_owner_timezone = $this->job_owner_timezone;
        $translatorStruct->added_by           = $this->callingUser->uid;
        $translatorStruct->email              = $this->email;
        $translatorStruct->source             = $this->jStruct[ 'source' ];
        $translatorStruct->target             = $this->jStruct[ 'target' ];

        $jTranslatorsDao->insertStruct( $translatorStruct, [
                'no_nulls'            => true,
                'on_duplicate_update' => [
                        'delivery_date'      => 'value',
                        'job_password'       => 'value',
                        'job_owner_timezone' => 'value'
                ]
        ] );

        //Update internal variable
        $this->jobTranslator = $translatorStruct;

        //clean cache JobsTranslatorsDao to update the delivery_date in next query
        $jTranslatorsDao->destroyCacheByJobStruct( $this->jStruct );

        $this->sendEmail();

        return $translatorStruct;

    }

    /**
     * @throws Exception
     */
    protected function saveProfile( Users_UserStruct $existentUser ) {

        //associate the translator with an existent user and create a profile
        $profileStruct                 = new TranslatorProfilesStruct();
        $profileStruct->uid_translator = $existentUser->uid;
        $profileStruct->is_revision    = 0;
        $profileStruct->source         = $this->jStruct[ 'source' ];
        $profileStruct->target         = $this->jStruct[ 'target' ];

        $tProfileDao           = new TranslatorsProfilesDao();
        $existentProfileStruct = $tProfileDao->getByProfile( $profileStruct );

        if ( empty( $existentProfileStruct ) ) {

            $profileStruct->id = $tProfileDao->insertStruct( $profileStruct, [
                    'no_nulls' => true
            ] );

            return $profileStruct->id;

        }

        return $existentProfileStruct->id;

    }

    /**
     * @throws Exception
     */
    public function changeJobPassword( $newPassword = null ) {

        if ( empty( $newPassword ) ) {
            $newPassword = Utils::randomString();
        }

        $oldPassword = $this->jStruct->password;

        $this->openTransaction();
        $jobDao = new Jobs_JobDao();
        $jobDao->changePassword( $this->jStruct, $newPassword );
        $jobDao->destroyCache( $this->jStruct );
        $this->featureSet->run( 'job_password_changed', $this->jStruct, $oldPassword );
        $this->commitTransaction();

    }

    protected function sendEmail() {

        if ( empty( $this->callingUser ) ) {
            throw new InvalidArgumentException( "Who invites can not be empty. Try TranslatorsModel::setUser() " );
        }

        $project = Projects_ProjectDao::findByJobId( $this->jStruct->id );

        foreach ( $this->mailsToBeSent as $type => $email ) {

            if ( empty( $email ) ) {
                continue;
            }

            switch ( $type ) {
                case 'new':
                    $mailSender = new SendToTranslatorForNewJobEmail( $this->callingUser, $this->jobTranslator, $project->name );
                    $mailSender->send();
                    break;
                case 'update':
                    $mailSender = new SendToTranslatorForDeliveryChangeEmail( $this->callingUser, $this->jobTranslator, $project->name );
                    $mailSender->send();
                    break;
                case 'split':
                    $mailSender = new SendToTranslatorForJobSplitEmail( $this->callingUser, $this->jobTranslator, $project->name );
                    $mailSender->send();
                    break;
            }

        }
    }

}