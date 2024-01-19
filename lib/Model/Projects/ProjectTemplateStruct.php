<?php

namespace Projects;

use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;
use PayableRates\CustomPayableRateDao;
use PayableRates\CustomPayableRateStruct;
use QAModelTemplate\QAModelTemplateDao;
use QAModelTemplate\QAModelTemplateStruct;
use Teams\TeamDao;
use Teams\TeamStruct;

class ProjectTemplateStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct, \JsonSerializable
{
    public $id;
    public $name;
    public $is_default;
    public $uid;
    public $id_team;
    public $speech2text;
    public $lexica;
    public $tag_projection;
    public $cross_language_matches;
    public $segmentation_rule;
    public $mt;
    public $tm;
    public $payable_rate_template_id;
    public $qa_model_template_id;
    public $pretranslate_100;
    public $get_public_matches;
    public $created_at ;
    public $modified_at ;

    /**
     * @param $json
     * @return $this
     * @throws \Exception
     */
    public function hydrateFromJSON($json)
    {
        $json = json_decode($json);

        $this->id = $json->id;
        $this->name = $json->name;
        $this->is_default = $json->is_default;
        $this->uid = $json->uid;
        $this->id_team = $json->id_team;
        $this->speech2text = $json->speech2text;
        $this->lexica = $json->lexica;
        $this->tag_projection = $json->tag_projection;
        $this->cross_language_matches = $json->cross_language_matches;
        $this->segmentation_rule = $json->segmentation_rule;
        $this->pretranslate_100 = $json->pretranslate_100;
        $this->get_public_matches = $json->get_public_matches;
        $this->mt = $json->mt;
        $this->tm = $json->tm;
        $this->payable_rate_template_id = $json->payable_rate_template_id;
        $this->qa_model_template_id = $json->qa_model_template_id;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getMt()
    {
        if(is_string($this->mt)){
            return json_decode($this->mt, true);
        }

        return $this->mt;
    }

    /**
     * @return array
     */
    public function getTm()
    {
        if(is_string($this->tm)){
            return json_decode($this->tm, true);
        }

        return $this->tm;
    }

    /**
     * @return string
     */
    public function tmToJson()
    {
        return (!empty($this->tm)) ? json_encode($this->tm) : null;
    }

    /**
     * @return string
     */
    public function mtToJson()
    {
        return (!empty($this->mt)) ? json_encode($this->mt) : null;
    }

    /**
     * @return TeamStruct|null
     */
    public function getTeam()
    {
        if ( is_null( $this->id_team ) ) {
            return null ;
        }

        $dao = new TeamDao();

        return $dao->findById( $this->id_team ) ;
    }

    /**
     * @return CustomPayableRateStruct|null
     */
    public function getPayableRate()
    {
        if ( is_null( $this->payable_rate_template_id ) ) {
            return null ;
        }

        return CustomPayableRateDao::getById($this->payable_rate_template_id );
    }

    /**
     * @return QAModelTemplateStruct|null
     */
    public function getQATemplateModel()
    {
        if ( is_null( $this->qa_model_template_id ) ) {
            return null ;
        }

        $qaModels = QAModelTemplateDao::get([
            'id' => $this->qa_model_template_id
        ]);

        if(count($qaModels) == 1){
            return $qaModels[0];
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return [
            'id' => (int)$this->id,
            'name' => $this->name,
            'is_default' => (bool)$this->is_default,
            'uid' => (int)$this->uid,
            'id_team' => (int)$this->id_team,
            'speech2text' => (bool)$this->speech2text,
            'lexica' => (bool)$this->lexica,
            'tag_projection' => (bool)$this->tag_projection,
            'cross_language_matches' => $this->cross_language_matches,
            'segmentation_rule' => $this->segmentation_rule,
            'mt' => $this->getMt(),
            'tm' => $this->getTm(),
            'payable_rate_template_id' => $this->payable_rate_template_id ? (int)$this->payable_rate_template_id : null,
            'qa_model_template_id' => $this->qa_model_template_id ? (int)$this->qa_model_template_id : null,
            'get_public_matches' => $this->get_public_matches,
            'pretranslate_100' => $this->pretranslate_100,
            'created_at' => date_create( $this->created_at )->format( DATE_RFC822 ),
            'modified_at' => date_create( $this->modified_at )->format( DATE_RFC822 ),
        ];
    }
}