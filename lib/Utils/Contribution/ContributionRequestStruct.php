<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 17/12/18
 * Time: 16.20
 *
 */

namespace Contribution;


use DataAccess\IDaoStruct;
use DataAccess\ShapelessConcreteStruct;
use Engine;
use Engines_AbstractEngine;
use Exception;
use FeatureSet;
use Jobs_JobStruct;
use Projects_ProjectStruct;
use Users_UserStruct;

class ContributionRequestStruct extends ShapelessConcreteStruct implements IDaoStruct {

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

    public bool   $mt_evaluation              = false;
    public int    $mt_quality_value_in_editor = 86;
    public bool   $mt_qe_workflow_enabled     = false;
    public string $mt_qe_workflow_parameters  = '{}';

    public array $penalty_key = [];


    ### NOT SERIALIZABLE Private members ###

    /**
     * @var ?Engines_AbstractEngine
     */
    private ?Engines_AbstractEngine $tmEngine = null;

    /**
     * @var ?Engines_AbstractEngine
     */
    private ?Engines_AbstractEngine $mt_engine = null;

    /**
     * @param Jobs_JobStruct $jobStruct
     *
     * @return $this
     */
    public function setJobStruct( Jobs_JobStruct $jobStruct ): ContributionRequestStruct {
        $this->jobStruct = $jobStruct->toArray();

        return $this;
    }

    /**
     * @param Projects_ProjectStruct $projectStruct
     *
     * @return $this
     */
    public function setProjectStruct( Projects_ProjectStruct $projectStruct ): ContributionRequestStruct {
        $this->projectStruct = $projectStruct->toArray();

        return $this;
    }

    /**
     * @param Users_UserStruct|null $user
     *
     * @return $this
     */
    public function setUser( Users_UserStruct $user ): ContributionRequestStruct {
        $this->user = $user->toArray();

        return $this;
    }


    /**
     * @return ?Jobs_JobStruct
     */
    public function getJobStruct(): ?Jobs_JobStruct {
        return new Jobs_JobStruct( $this->jobStruct );
    }

    /**
     * @return Projects_ProjectStruct
     */
    public function getProjectStruct(): ?Projects_ProjectStruct {
        return new Projects_ProjectStruct( $this->projectStruct );
    }

    /**
     * @param FeatureSet $featureSet
     *
     * @return Engines_AbstractEngine
     * @throws Exception
     */
    public function getTMEngine( FeatureSet $featureSet ): Engines_AbstractEngine {
        if ( $this->tmEngine == null ) {
            $this->tmEngine = Engine::getInstance( $this->getJobStruct()->id_tms );
            $this->tmEngine->setFeatureSet( $featureSet );
        }

        return $this->tmEngine;
    }

    /**
     * @param FeatureSet $featureSet
     *
     * @return Engines_AbstractEngine
     * @throws Exception
     */
    public function getMTEngine( FeatureSet $featureSet ): Engines_AbstractEngine {
        if ( $this->mt_engine == null ) {
            $this->mt_engine = Engine::getInstance( $this->getJobStruct()->id_mt_engine );
            $this->mt_engine->setFeatureSet( $featureSet );
        }

        return $this->mt_engine;
    }

    public function getContexts(): object {
        return (object)$this->contexts;
    }

    /**
     * @return ?Users_UserStruct
     */
    public function getUser(): ?Users_UserStruct {
        return new Users_UserStruct( $this->user );
    }

    /**
     * @return string
     */
    public function getSessionId(): string {
        return md5( $this->id_file . '-' . $this->id_job . '-' . $this->password );
    }
}