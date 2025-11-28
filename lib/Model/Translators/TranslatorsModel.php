<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 14/04/17
 * Time: 17.08
 *
 */

namespace Model\Translators;


use Controller\Abstracts\KleinController;
use Exception;
use InvalidArgumentException;
use Model\DataAccess\TransactionalTrait;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\Outsource\ConfirmationDao;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use ReflectionException;
use Utils\Email\SendToTranslatorForDeliveryChangeEmail;
use Utils\Email\SendToTranslatorForJobSplitEmail;
use Utils\Email\SendToTranslatorForNewJobEmail;
use Utils\Tools\Utils;

class TranslatorsModel
{

    use TransactionalTrait;

    /**
     * @var ?UserStruct
     */
    protected ?UserStruct $callingUser = null;

    /**
     * @var JobStruct
     */
    protected JobStruct $jStruct;

    /**
     * @var KleinController
     */
    protected KleinController $controller;

    /**
     * @var array
     */
    protected array $mailsToBeSent = ['new' => null, 'update' => null, 'split' => null];

    protected int    $delivery_date;
    protected int    $job_owner_timezone = 0;
    protected ?int   $id_job;
    protected string $email;
    protected string $job_password;

    /**
     * @var ProjectStruct
     */
    protected ProjectStruct $project;

    /**
     * @var FeatureSet
     */
    protected FeatureSet $featureSet;

    /**
     * Override the Job Password from Outside
     *
     * @param mixed $job_password
     *
     * @return $this
     */
    public function setNewJobPassword(string $job_password): TranslatorsModel
    {
        $this->job_password = $job_password;

        return $this;
    }

    /**
     * @var JobsTranslatorsStruct|null
     */
    protected ?JobsTranslatorsStruct $jobTranslator = null;

    /**
     * @param string|int $delivery_date
     *
     * @return $this
     */
    public function setDeliveryDate(int|string $delivery_date): TranslatorsModel
    {
        if (is_numeric($delivery_date) && (int)$delivery_date == $delivery_date) {
            $this->delivery_date = $delivery_date;
        } else {
            $this->delivery_date = strtotime($delivery_date);
        }

        return $this;
    }

    /**
     * @param int $job_owner_timezone
     *
     * @return $this
     */
    public function setJobOwnerTimezone(int $job_owner_timezone): TranslatorsModel
    {
        $this->job_owner_timezone = $job_owner_timezone;

        return $this;
    }

    /**
     * @param string $email
     *
     * @return $this
     */
    public function setEmail(string $email): TranslatorsModel
    {
        $this->email = $email;

        return $this;
    }

    public function setUserInvite(UserStruct $user): TranslatorsModel
    {
        $this->callingUser = $user;

        return $this;
    }

    /**
     * TranslatorsModel constructor.
     *
     * @param JobStruct $jStruct
     * @param int       $project_cache_TTL
     */
    public function __construct(JobStruct $jStruct, int $project_cache_TTL = 60 * 60)
    {
        //get the job
        $this->jStruct = $jStruct;

        $this->id_job       = $jStruct->id;
        $this->job_password = $jStruct->password;

        $this->project    = $this->jStruct->getProject($project_cache_TTL);
        $this->featureSet = $this->project->getFeaturesSet();
    }

    /**
     * @throws ReflectionException
     */
    public function getTranslator(int $cache = 86400)
    {
        $jTranslatorsDao = new JobsTranslatorsDao();

        return $this->jobTranslator = $jTranslatorsDao->setCacheTTL($cache)->findByJobsStruct($this->jStruct)[ 0 ] ?? null;
    }

    /**
     * @return JobsTranslatorsStruct
     * @throws Exception
     */
    public function update(): JobsTranslatorsStruct
    {
        $confDao            = new ConfirmationDao();
        $confirmationStruct = $confDao->getConfirmation($this->jStruct);

        if (!empty($confirmationStruct)) {
            throw new InvalidArgumentException("The Job is Outsourced.", 400);
        }

        //create jobs_translator struct to call inside the dao
        $translatorStruct = new JobsTranslatorsStruct();

        $translatorUser = (new UserDao())->setCacheTTL(60 * 60)->getByEmail($this->email);
        if (!empty($translatorUser)) {
            //associate the translator with an existent user and create a profile if not exists
            $translatorStruct->id_translator_profile = $this->saveProfile($translatorUser);
        }

        $jTranslatorsDao = new JobsTranslatorsDao();
        if (empty($this->jobTranslator)) { // self::getTranslator() can be called from outside
            // retrieve with no cache
            $this->getTranslator(0);
        }

        if (!empty($this->jobTranslator)) { // an associated translator already exists for this chunk

            if ($this->jobTranslator->email != $this->email) {
                //if the translator email changed ( differs from the existing one ), change the Job Password and insert a new row
                $this->changeJobPassword();

                //send a mail to the new translator
                $this->mailsToBeSent[ 'new' ] = $this->email;
            } elseif (strtotime($this->jobTranslator->delivery_date) != $this->delivery_date) {
                //send a mail to the translator if delivery_date changes
                $this->mailsToBeSent[ 'update' ] = $this->email;
            } elseif ($this->jobTranslator->job_password != $this->job_password) {
                $this->changeJobPassword($this->job_password);

                $this->mailsToBeSent[ 'split' ] = $this->email;
            }
        } else {
            //send a mail to the new translator
            $this->mailsToBeSent[ 'new' ] = $this->email;
        }

        //set the old id and password to make "ON DUPLICATE KEY UPDATE" possible
        $translatorStruct->id_job             = $this->jStruct->id;
        $translatorStruct->job_password       = $this->jStruct->password;
        $translatorStruct->delivery_date      = Utils::mysqlTimestamp($this->delivery_date);
        $translatorStruct->job_owner_timezone = $this->job_owner_timezone;
        $translatorStruct->added_by           = $this->callingUser->uid;
        $translatorStruct->email              = $this->email;
        $translatorStruct->source             = $this->jStruct[ 'source' ];
        $translatorStruct->target             = $this->jStruct[ 'target' ];

        $jTranslatorsDao->insertStruct($translatorStruct, [
                'no_nulls'            => true,
                'on_duplicate_update' => [
                        'delivery_date'      => 'value',
                        'job_password'       => 'value',
                        'job_owner_timezone' => 'value'
                ]
        ]);

        //Update internal variable
        $this->jobTranslator = $translatorStruct;

        //clean cache JobsTranslatorsDao to update the delivery_date in next query
        $jTranslatorsDao->destroyCacheByJobStruct($this->jStruct);

        $this->sendEmail();

        return $translatorStruct;
    }

    /**
     * @throws Exception
     */
    protected function saveProfile(UserStruct $existentUser): int
    {
        //associate the translator with an existent user and create a profile
        $profileStruct                 = new TranslatorProfilesStruct();
        $profileStruct->uid_translator = $existentUser->uid;
        $profileStruct->is_revision    = 0;
        $profileStruct->source         = $this->jStruct[ 'source' ];
        $profileStruct->target         = $this->jStruct[ 'target' ];

        $tProfileDao           = new TranslatorsProfilesDao();
        $existentProfileStruct = $tProfileDao->getByProfile($profileStruct);

        if (empty($existentProfileStruct)) {
            $profileStruct->id = $tProfileDao->insertStruct($profileStruct, [
                    'no_nulls' => true
            ]);

            return $profileStruct->id;
        }

        return $existentProfileStruct->id;
    }

    /**
     * @throws Exception
     */
    public function changeJobPassword(?string $newPassword = null): void
    {
        if (empty($newPassword)) {
            $newPassword = Utils::randomString();
        }

        $oldPassword = $this->jStruct->password;

        $this->openTransaction();
        $jobDao = new JobDao();
        $jobDao->changePassword($this->jStruct, $newPassword);
        $jobDao->destroyCache($this->jStruct);
        $this->featureSet->run('job_password_changed', $this->jStruct, $oldPassword);
        $this->commitTransaction();
    }

    /**
     * @throws Exception
     */
    protected function sendEmail(): void
    {
        if (empty($this->callingUser)) {
            throw new InvalidArgumentException("Who invites can not be empty. Try TranslatorsModel::setUser() ");
        }

        $project = ProjectDao::findByJobId($this->jStruct->id);

        foreach ($this->mailsToBeSent as $type => $email) {
            if (empty($email)) {
                continue;
            }

            switch ($type) {
                case 'new':
                    $mailSender = new SendToTranslatorForNewJobEmail($this->callingUser, $this->jobTranslator, $project->name);
                    $mailSender->send();
                    break;
                case 'update':
                    $mailSender = new SendToTranslatorForDeliveryChangeEmail($this->callingUser, $this->jobTranslator, $project->name);
                    $mailSender->send();
                    break;
                case 'split':
                    $mailSender = new SendToTranslatorForJobSplitEmail($this->callingUser, $this->jobTranslator, $project->name);
                    $mailSender->send();
                    break;
            }
        }
    }

}