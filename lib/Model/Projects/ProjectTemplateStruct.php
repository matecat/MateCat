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
    public $created_at ;
    public $modified_at ;

    /**
     * @return string
     */
    public function tmToJson()
    {
        return json_encode($this->tm);
    }

    /**
     * @return string
     */
    public function mtToJson()
    {
        return json_encode($this->mt);
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
            'id' => $this->id,
            'name' => $this->name,
            'is_default' => $this->is_default,
            'uid' => $this->uid,
            'id_team' => $this->id_team,
            'speech2text' => $this->speech2text,
            'lexica' => $this->lexica,
            'tag_projection' => $this->tag_projection,
            'cross_language_matches' => $this->cross_language_matches,
            'segmentation_rule' => $this->segmentation_rule,
            'mt' => $this->mt,
            'tm' => $this->tm,
            'payable_rate_template_id' => $this->payable_rate_template_id,
            'qa_model_template_id' => $this->qa_model_template_id,
            'create_date' => date_create( $this->create_date )->format( DATE_RFC822 ),
            'update_date' => date_create( $this->update_date )->format( DATE_RFC822 ),
        ];
    }
}