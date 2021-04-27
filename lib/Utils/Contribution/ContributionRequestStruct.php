<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 17/12/18
 * Time: 16.20
 *
 */

namespace Contribution;


use Chunks_ChunkStruct;
use DataAccess\ShapelessConcreteStruct;
use DataAccess_IDaoStruct;
use Projects_ProjectStruct;

class ContributionRequestStruct extends ShapelessConcreteStruct implements DataAccess_IDaoStruct {

    public $jobStruct;

    public $dataRefMap;

    public $projectStruct;

    public $contexts = [
            'context_before' => null,
            'segment'        => null,
            'context_after'  => null
    ];

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


    # Private members
    /**
     * @var \Jobs_JobStruct|\Chunks_ChunkStruct
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
     * @return Chunks_ChunkStruct|\Jobs_JobStruct
     */
    public function getJobStruct(){
        if( $this->__jobStruct == null ){
            $this->__jobStruct = new Chunks_ChunkStruct( (array)$this->jobStruct );
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

}