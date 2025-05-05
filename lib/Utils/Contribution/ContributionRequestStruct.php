<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 17/12/18
 * Time: 16.20
 *
 */

namespace Contribution;


use DataAccess\ShapelessConcreteStruct;
use DataAccess_IDaoStruct;
use Jobs_JobStruct;
use Projects_ProjectStruct;

class ContributionRequestStruct extends ShapelessConcreteStruct implements DataAccess_IDaoStruct {

    // Needed by getSessionId()
    public $id_file;
    public $id_job;
    public $password;

    public $jobStruct;

    public $dataRefMap = [];

    public $projectStruct;

    public $contexts = [
        'context_before' => null,
        'segment'        => null,
        'context_after'  => null
    ];

    public ?array $context_list_before = null;
    public ?array $context_list_after  = null;

    /**
     * @var string
     */
    public $id_client;

    /**
     * @var \Users_UserStruct
     */
    public $user;

    /**
     * @var string
     */
    public $userRole;

    /**
     * @var int
     */
    public $segmentId = null;

    /**
     * @var int
     */
    public $resultNum = 3;

    /**
     * @var bool
     */
    public $concordanceSearch = false;

    /**
     * @var bool
     */
    public $fromTarget = false;

    public $crossLangTargets = [] ;

    public $dialect_strict = null;

    public $tm_prioritization = null;

    public $mt_evaluation = null;
    public ?string $mt_qe_engine_id = null;

    public $penalty_key = [];

    # Private members
    /**
     * @var \Jobs_JobStruct|\Jobs_JobStruct
     */
    private $__jobStruct = null;

    /**
     * @var \Projects_ProjectStruct
     */
    private $__projectStruct = null;

    /**
     * @var \Users_UserStruct
     */
    private $__user = null;

    /**
     * @var \Engines_AbstractEngine
     */
    private $__tms = null;

    /**
     * @var \Engines_AbstractEngine
     */
    private $__mt_engine = null;

    /**
     * @return Jobs_JobStruct|\Jobs_JobStruct
     */
    public function getJobStruct(){
        if( $this->__jobStruct == null ){
            $this->__jobStruct = new Jobs_JobStruct( (array)$this->jobStruct );
        }
        return $this->__jobStruct;
    }

    /**
     * @return Projects_ProjectStruct
     */
    public function getProjectStruct(){
        if( $this->__projectStruct == null ){
            $this->__projectStruct = new Projects_ProjectStruct( (array)$this->projectStruct );
        }
        return $this->__projectStruct;
    }

    /**
     * @param \FeatureSet $featureSet
     *
     * @return \Engines_AbstractEngine
     * @throws \Exception
     */
    public function getTMEngine( \FeatureSet $featureSet ){
        if( $this->__tms == null ){
            $this->__tms = \Engine::getInstance( $this->getJobStruct()->id_tms );
            $this->__tms->setFeatureSet( $featureSet );
        }
        return $this->__tms;
    }

    /**
     * @param \FeatureSet $featureSet
     *
     * @return \Engines_AbstractEngine
     * @throws \Exception
     */
    public function getMTEngine( \FeatureSet $featureSet ){
        if( $this->__mt_engine == null ){
            $this->__mt_engine = \Engine::getInstance( $this->getJobStruct()->id_mt_engine );
            $this->__mt_engine->setFeatureSet( $featureSet );
        }
        return $this->__mt_engine;
    }

    public function getContexts(){
        return (object)$this->contexts;
    }

    public function getUser(){
        if( $this->__user == null ){
            $this->__user = new \Users_UserStruct( (array)$this->user );
        }
        return $this->__user;
    }

    /**
     * @return string
     */
    public function getSessionId()
    {
        return md5($this->id_file. '-' . $this->id_job . '-' . $this->password);
    }
}