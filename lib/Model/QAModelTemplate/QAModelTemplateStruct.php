<?php

namespace QAModelTemplate;

use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;

class QAModelTemplateStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct, \JsonSerializable
{
    public $id;
    public $uid;
    public $label;
    public $version;

    /**
     * @var QAModelTemplatePassfailStruct
     */
    public $passfail;

    /**
     * @var QAModelTemplateCategoryStruct[]
     */
    public $categories = [];

    /**
     * @param $json
     *
     * @return QAModelTemplateStruct
     * @throws \Exception
     */
    public static function hydrateFromJSON($json)
    {
        $json = json_decode($json);

        if(!isset($json->model)){
            throw new \Exception("Cannot instantiate a new QAModelTemplateStruct. Invalid JSON provided.");
        }

        $jsonModel = $json->model;

        // QAModelTemplateStruct
        $QAModelTemplateStruct = new QAModelTemplateStruct();
        $QAModelTemplateStruct->id = (isset($jsonModel->id)) ? $jsonModel->id : null;
        $QAModelTemplateStruct->version = $jsonModel->version;
        $QAModelTemplateStruct->label = $jsonModel->label;

        // QAModelTemplatePassfailStruct
        $QAModelTemplatePassfailStruct = new QAModelTemplatePassfailStruct();
        $QAModelTemplatePassfailStruct->id = (isset($jsonModel->passfail->id)) ? $jsonModel->passfail->id : null;
        $QAModelTemplatePassfailStruct->passfail_type = $jsonModel->passfail->type;
        $QAModelTemplatePassfailStruct->id_template = (isset($jsonModel->id)) ? $jsonModel->id : null;

        foreach ($jsonModel->passfail->thresholds as $threshold){
            $modelTemplatePassfailThresholdStruct = new QAModelTemplatePassfailThresholdStruct();
            $modelTemplatePassfailThresholdStruct->id = (isset($threshold->id)) ? $threshold->id : null;
            $modelTemplatePassfailThresholdStruct->passfail_label = $threshold->label;
            $modelTemplatePassfailThresholdStruct->passfail_value = $threshold->value;

            $QAModelTemplatePassfailStruct->thresholds[] = $modelTemplatePassfailThresholdStruct;
        }

        $QAModelTemplateStruct->passfail = $QAModelTemplatePassfailStruct;

        // QAModelTemplateCategoryStruct[]
        foreach ($jsonModel->categories as $category){

            $QAModelTemplateCategoryStruct = new QAModelTemplateCategoryStruct();
            $QAModelTemplateCategoryStruct->id = (isset($category->id)) ? $category->id : null;
            $QAModelTemplateCategoryStruct->id_template = (isset($jsonModel->id)) ? $jsonModel->id : null;
            $QAModelTemplateCategoryStruct->id_parent = (isset($jsonModel->id_parent)) ? $jsonModel->id_parent : null;
            $QAModelTemplateCategoryStruct->category_label = $category->label;
            $QAModelTemplateCategoryStruct->code  = $category->code;

            foreach ($category->severities as $severity){
                $severityModel = new QAModelTemplateSeverityStruct();
                $severityModel->id = (isset($severity->id)) ? $severity->id : null;
                $severityModel->id_category = (isset($category->id)) ? $category->id : null;
                $severityModel->severity_label = $severity->label;
                $severityModel->code  = $severity->code;
                $severityModel->penalty  = $severity->penalty;
                $severityModel->dqf_id  = (isset($severity->dqf_id)) ? $severity->dqf_id : null;

                $QAModelTemplateCategoryStruct->severities[] = $severityModel;
            }

            $QAModelTemplateStruct->categories[] = $QAModelTemplateCategoryStruct;
        }

        return $QAModelTemplateStruct;
    }

    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'uid' => $this->uid,
            'label' => $this->label,
            'version' => $this->version,
            'categories' => $this->categories,
            'passfail' => $this->passfail,
        ];
    }
}