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
use CatUtils;
use InvalidArgumentException;
use Jobs_JobDao;
use Jobs_JobStruct;
use Outsource\ConfirmationDao;
use Users_UserStruct;
use Utils;

class TranslatorsModel {

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
    protected $mailsToBeSent = [ 'new' => null, 'update' => null, 'change' => null ];

    /**
     * TranslatorsModel constructor.
     *
     * @param KleinController $controller
     * @param Jobs_JobStruct  $jStruct
     */
    public function __construct( KleinController $controller, Jobs_JobStruct $jStruct ) {

        $this->controller = $controller;

        //get the job
        $this->jStruct = $jStruct;
        $this->callingUser = $controller->getUser();

    }

    public function getTranslator(){

        $jTranslatorsDao = new JobsTranslatorsDao();
        $jTranslatorsStruct = $jTranslatorsDao->setCacheTTL( 60 * 60 * 24 )->findByJobsStruct( $this->jStruct )[ 0 ];

        return $jTranslatorsStruct;

    }

    public function update(){

        $confDao = new ConfirmationDao();
        $confirmationStruct = $confDao->getConfirmation( $this->jStruct );

        if( !empty( $confirmationStruct ) ){
            throw new InvalidArgumentException( "The Job is Outsourced.", 400 );
        }

        //create jobs_translator struct to call inside the dao
        $jTranslatorsStruct = new JobsTranslatorsStruct();

        //set the old id and password to make "ON DUPLICATE KEY UPDATE" possible
        $jTranslatorsStruct->id_job        = $this->controller->params[ 'id_job' ];
        $jTranslatorsStruct->job_password  = $this->controller->params[ 'password' ];

        $existentUser = ( new \Users_UserDao() )->setCacheTTL( 60 * 60 )->getByEmail( $this->controller->params[ 'email' ] );
        if ( !empty( $existentUser ) ) {

            //associate the translator with an existent user
            $profileStruct = new TranslatorProfilesStruct();
            $profileStruct->uid_translator = $existentUser->uid;
            $profileStruct->is_revision = 0;
            $profileStruct->source = $this->jStruct[ 'source' ];
            $profileStruct->target = $this->jStruct[ 'target' ];

            $tProfileDao = new TranslatorsProfilesDao();
            $profileStruct->id = $tProfileDao->insertStruct( $profileStruct, [
                    'no_nulls' => true,
                    'ignore'   => true
            ] );


            $jTranslatorsStruct->id_translator_profile = $profileStruct->id;

        }

        $jTranslatorsDao = new JobsTranslatorsDao();
        $existentRecord = $jTranslatorsDao->findByJobsStruct( $this->jStruct )[ 0 ];

        if( !empty( $existentRecord ) ){

            if( $existentRecord->email != $this->controller->params['email'] ){

                //if the translator email changed ( differs from the existing one ), change the Job Password and insert a new row
                $this->changeJobPassword();

                $jTranslatorsStruct->job_password  = $this->jStruct->password;

                //TODO send a mail to the new translator
                $this->mailsToBeSent[ 'new' ] = $this->controller->params[ 'email' ];

            } elseif( strtotime( $existentRecord->delivery_date ) != $this->controller->params[ 'delivery_date' ] ) {

                //TODO send a mail to the translator if delivery_date changes
                $this->mailsToBeSent[ 'update' ] = $this->controller->params[ 'email' ];

            }

        }

        $jTranslatorsStruct->delivery_date = Utils::mysqlTimestamp( $this->controller->params[ 'delivery_date' ] );
        $jTranslatorsStruct->added_by      = $this->callingUser->uid;
        $jTranslatorsStruct->email         = $this->controller->params[ 'email' ];
        $jTranslatorsStruct->source        = $this->jStruct[ 'source' ];
        $jTranslatorsStruct->target        = $this->jStruct[ 'target' ];

        $jTranslatorsDao->insertStruct( $jTranslatorsStruct, [
                'no_nulls' => true,
                'on_duplicate_update' => [
                        'delivery_date = VALUES( delivery_date )'
                ]
        ] );

        //clean cache JobsTranslatorsDao to update the delivery_date in next query
        $jTranslatorsDao->destroyCacheByJobStruct( $this->jStruct );

        return $jTranslatorsStruct;

    }

    public function changeJobPassword(){

        $jobDao = new Jobs_JobDao();
        $jobDao->destroyCache( $this->jStruct );
        $jobDao->changePassword( $this->jStruct, CatUtils::generate_password() );

    }

    public function sendEmail(){

    }

}