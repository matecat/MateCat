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
use Model\FeaturesBase\Hook\Event\Run\JobPasswordChangedEvent;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\Outsource\ConfirmationDao;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use ReflectionException;
use RuntimeException;
use TypeError;
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
     * @var array{new: ?string, update: ?string, split: ?string}
     */
    protected array $mailsToBeSent = ['new' => null, 'update' => null, 'split' => null];

    protected int $delivery_date;
    protected float $job_owner_timezone = 0;
    protected ?int $id_job;
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
     * @param string $job_password
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
     *
     * @throws TypeError
     */
    public function setDeliveryDate(int|string $delivery_date): TranslatorsModel
    {
        if (is_numeric($delivery_date) && (int)$delivery_date == $delivery_date) {
            $this->delivery_date = (int)$delivery_date;
        } else {
            if (!is_string($delivery_date)) {
                throw new TypeError('delivery_date must be a numeric int or a date string');
            }
            $timestamp = strtotime($delivery_date);
            if ($timestamp === false) {
                throw new TypeError("Invalid date string: $delivery_date");
            }
            $this->delivery_date = $timestamp;
        }

        return $this;
    }

    /**
     * @param int $job_owner_timezone
     *
     * @return $this
     */
    public function setJobOwnerTimezone(float|int $job_owner_timezone): TranslatorsModel
    {
        $this->job_owner_timezone = (float)$job_owner_timezone;

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
     * @param int $project_cache_TTL
     *
     * @throws TypeError
     */
    public function __construct(JobStruct $jStruct, int $project_cache_TTL = 60 * 60)
    {
        //get the job
        $this->jStruct = $jStruct;

        $this->id_job = $jStruct->id;
        $this->job_password = $jStruct->password ?? throw new TypeError('JobStruct::$password cannot be null');

        $this->project = $this->jStruct->getProject($project_cache_TTL);
        $this->featureSet = $this->project->getFeaturesSet();
    }

    /**
     * @return JobsTranslatorsStruct|null
     *
     * @throws ReflectionException
     * @throws Exception
     */
    public function getTranslator(int $cache = 86400): ?JobsTranslatorsStruct
    {
        $jTranslatorsDao = new JobsTranslatorsDao();

        return $this->jobTranslator = $jTranslatorsDao->setCacheTTL($cache)->findByJobsStruct($this->jStruct)[0] ?? null;
    }

    /**
     * @return JobsTranslatorsStruct
     *
     * @throws Exception
     * @throws TypeError
     */
    public function update(): JobsTranslatorsStruct
    {
        $confDao = new ConfirmationDao();
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
                $this->mailsToBeSent['new'] = $this->email;
            } elseif (strtotime($this->jobTranslator->delivery_date) != $this->delivery_date) {
                //send a mail to the translator if delivery_date changes
                $this->mailsToBeSent['update'] = $this->email;
            } elseif ($this->jobTranslator->job_password != $this->job_password) {
                $this->changeJobPassword($this->job_password);

                $this->mailsToBeSent['split'] = $this->email;
            }
        } else {
            //send a mail to the new translator
            $this->mailsToBeSent['new'] = $this->email;
        }

        //set the old id and password to make "ON DUPLICATE KEY UPDATE" possible
        $translatorStruct->id_job = $this->jStruct->id ?? throw new TypeError('JobStruct::$id cannot be null');
        $translatorStruct->job_password = $this->jStruct->password ?? throw new TypeError('JobStruct::$password cannot be null');
        $translatorStruct->delivery_date = Utils::mysqlTimestamp($this->delivery_date);
        $translatorStruct->job_owner_timezone = $this->job_owner_timezone;
        $translatorStruct->added_by = $this->callingUser->uid ?? throw new TypeError('Calling user uid cannot be null');
        $translatorStruct->email = $this->email;
        $translatorStruct->source = $this->jStruct['source'];
        $translatorStruct->target = $this->jStruct['target'];

        $jTranslatorsDao->insertStruct($translatorStruct, [
            'no_nulls' => true,
            'on_duplicate_update' => [
                'delivery_date' => 'value',
                'job_password' => 'value',
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
     * @throws TypeError
     */
    protected function saveProfile(UserStruct $existentUser): int
    {
        //associate the translator with an existent user and create a profile
        $profileStruct = new TranslatorProfilesStruct();
        $profileStruct->uid_translator = $existentUser->uid ?? throw new TypeError('UserStruct::$uid cannot be null');
        $profileStruct->is_revision = 0;
        $profileStruct->source = $this->jStruct['source'];
        $profileStruct->target = $this->jStruct['target'];

        $tProfileDao = new TranslatorsProfilesDao();
        $existentProfileStruct = $tProfileDao->getByProfile($profileStruct);

        if (empty($existentProfileStruct)) {
            $insertId = $tProfileDao->insertStruct($profileStruct, [
                'no_nulls' => true
            ]);

            if ($insertId === false) {
                throw new RuntimeException('Failed to insert translator profile');
            }

            $profileStruct->id = $insertId;

            return $profileStruct->id;
        }

        return $existentProfileStruct->id ?? throw new RuntimeException('Existing profile has no id');
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    public function changeJobPassword(?string $newPassword = null): void
    {
        if (empty($newPassword)) {
            $newPassword = Utils::randomString();
        }

        $oldPassword = $this->jStruct->password ?? throw new TypeError('JobStruct::$password cannot be null');

        $this->openTransaction();
        $jobDao = new JobDao();
        $jobDao->changePassword($this->jStruct, $newPassword);
        $jobDao->destroyCacheByIdAndPassword($this->jStruct);
        $this->featureSet->dispatchRun(new JobPasswordChangedEvent($this->jStruct, $oldPassword));
        $this->commitTransaction();
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    protected function sendEmail(): void
    {
        if (empty($this->callingUser)) {
            throw new InvalidArgumentException("Who invites can not be empty. Try TranslatorsModel::setUser() ");
        }

        $project = ProjectDao::findByJobId($this->jStruct->id ?? throw new TypeError('JobStruct::$id cannot be null'));

        if ($project === null) {
            throw new RuntimeException('Project not found for job id ' . $this->jStruct->id);
        }

        foreach ($this->mailsToBeSent as $type => $email) {
            if (empty($email)) {
                continue;
            }

            switch ($type) {
                case 'new':
                    $mailSender = new SendToTranslatorForNewJobEmail($this->callingUser, $this->jobTranslator ?? throw new RuntimeException('jobTranslator not set'), $project->name);
                    $mailSender->send();
                    break;
                case 'update':
                    $mailSender = new SendToTranslatorForDeliveryChangeEmail($this->callingUser, $this->jobTranslator ?? throw new RuntimeException('jobTranslator not set'), $project->name);
                    $mailSender->send();
                    break;
                case 'split':
                    $mailSender = new SendToTranslatorForJobSplitEmail($this->callingUser, $this->jobTranslator ?? throw new RuntimeException('jobTranslator not set'), $project->name);
                    $mailSender->send();
                    break;
            }
        }
    }

}
