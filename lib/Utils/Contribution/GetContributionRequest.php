<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 17/12/18
 * Time: 16.20
 *
 */

namespace Utils\Contribution;


use Exception;
use Model\DataAccess\AbstractDaoObjectStruct;
use Model\DataAccess\IDaoStruct;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use Model\Users\UserStruct;
use Utils\Engines\AbstractEngine;
use Utils\Engines\EnginesFactory;

class GetContributionRequest extends AbstractDaoObjectStruct implements IDaoStruct
{

    // Needed by getSessionId()
    public ?int    $id_file  = null;
    public ?int    $id_job   = null;
    public ?string $password = null;

    public ?array $jobStruct = [];

    public array $dataRefMap = [];

    /**
     * @var ?array
     */
    public ?array $projectStruct = [];

    public ?string $translation = null; // is set in the case of Lara Think

    public array $contexts = [
            'context_before' => null,
            'segment'        => null,
            'context_after'  => null
    ];

    public ?array $context_list_before = null;
    public ?array $context_list_after  = null;

    /**
     * @var string
     */
    public string $id_client;

    /**
     * @var ?array
     */
    public ?array $user = [];

    /**
     * @var string
     */
    public string $userRole;

    /**
     * @var int|null
     */
    public ?int $segmentId = null;

    /**
     * @var int
     */
    public int $resultNum = 3;

    /**
     * @var bool
     */
    public bool $concordanceSearch = false;

    /**
     * @var bool
     */
    public bool $fromTarget = false;

    public array $crossLangTargets = [];

    public bool $dialect_strict = false;

    public bool $tm_prioritization = false;

    public bool    $mt_evaluation              = false;
    public int     $mt_quality_value_in_editor = 86;
    public bool    $mt_qe_workflow_enabled     = false;
    public ?string $mt_qe_workflow_parameters  = null;

    public ?int $public_tm_penalty = null;

    public ?array $subfiltering_handlers = [];

    ### NOT SERIALIZABLE Private members ###

    /**
     * @var ?AbstractEngine
     */
    private ?AbstractEngine $tmEngine = null;

    /**
     * @var ?AbstractEngine
     */
    private ?AbstractEngine $mt_engine = null;

    /**
     * @param JobStruct $jobStruct
     *
     * @return $this
     */
    public function setJobStruct(JobStruct $jobStruct): GetContributionRequest
    {
        $this->jobStruct = $jobStruct->toArray();

        return $this;
    }

    /**
     * @param ProjectStruct $projectStruct
     *
     * @return $this
     */
    public function setProjectStruct(ProjectStruct $projectStruct): GetContributionRequest
    {
        $this->projectStruct = $projectStruct->toArray();

        return $this;
    }

    /**
     * @param UserStruct $user
     *
     * @return $this
     */
    public function setUser(UserStruct $user): GetContributionRequest
    {
        $this->user = $user->toArray();

        return $this;
    }


    /**
     * @return ?JobStruct
     */
    public function getJobStruct(): ?JobStruct
    {
        return new JobStruct($this->jobStruct);
    }

    /**
     * @return ProjectStruct|null
     */
    public function getProjectStruct(): ?ProjectStruct
    {
        return new ProjectStruct($this->projectStruct);
    }

    /**
     * @param FeatureSet $featureSet
     *
     * @return AbstractEngine
     * @throws Exception
     */
    public function getTMEngine(FeatureSet $featureSet): AbstractEngine
    {
        if ($this->tmEngine == null) {
            $this->tmEngine = EnginesFactory::getInstance($this->getJobStruct()->id_tms);
            $this->tmEngine->setFeatureSet($featureSet);
        }

        return $this->tmEngine;
    }

    /**
     * @param FeatureSet $featureSet
     *
     * @return AbstractEngine
     * @throws Exception
     */
    public function getMTEngine(FeatureSet $featureSet): AbstractEngine
    {
        if ($this->mt_engine == null) {
            $this->mt_engine = EnginesFactory::getInstance($this->getJobStruct()->id_mt_engine);
            $this->mt_engine->setFeatureSet($featureSet);
        }

        return $this->mt_engine;
    }

    public function getContexts(): object
    {
        return (object)$this->contexts;
    }

    /**
     * @return ?UserStruct
     */
    public function getUser(): ?UserStruct
    {
        return new UserStruct($this->user);
    }

    /**
     * @return string
     */
    public function getSessionId(): string
    {
        return md5($this->id_file . '-' . $this->id_job . '-' . $this->password);
    }
}