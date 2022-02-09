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
    public function hydrateFromJSON($json)
    {
        $json = json_decode($json);

        if(!isset($json->model)){
            throw new \Exception("Cannot instantiate a new QAModelTemplateStruct. Invalid JSON provided.");
        }

        $jsonModel = $json->model;

        // QAModelTemplateStruct
        $QAModelTemplateStruct = $this;
        $QAModelTemplateStruct->version = $jsonModel->version;
        $QAModelTemplateStruct->label = $jsonModel->label;

        // QAModelTemplatePassfailStruct
        $QAModelTemplatePassfailStruct = (!empty($QAModelTemplateStruct->passfail)) ? $QAModelTemplateStruct->passfail : new QAModelTemplatePassfailStruct();
        $QAModelTemplatePassfailStruct->passfail_type = $jsonModel->passfail->type;
        $QAModelTemplatePassfailStruct->id_template = (isset($jsonModel->id)) ? $jsonModel->id : null;

        foreach ($jsonModel->passfail->thresholds as $index => $threshold){

            $modelTemplatePassfailThresholdStruct = (!empty($QAModelTemplateStruct->passfail->thresholds[$index])) ? $QAModelTemplateStruct->passfail->thresholds[$index] : new
            QAModelTemplatePassfailThresholdStruct();
            $modelTemplatePassfailThresholdStruct->passfail_label = $threshold->label;
            $modelTemplatePassfailThresholdStruct->passfail_value = $threshold->value;

            $QAModelTemplatePassfailStruct->thresholds[$index] = $modelTemplatePassfailThresholdStruct;
        }

        $QAModelTemplateStruct->passfail = $QAModelTemplatePassfailStruct;

        // QAModelTemplateCategoryStruct[]
        foreach ($jsonModel->categories as $index => $category){

            $QAModelTemplateCategoryStruct = (!empty($QAModelTemplateStruct->categories[$index])) ? $QAModelTemplateStruct->categories[$index] : new QAModelTemplateCategoryStruct();
            $QAModelTemplateCategoryStruct->id_template = (isset($QAModelTemplateStruct->id)) ? $QAModelTemplateStruct->id : null;
            $QAModelTemplateCategoryStruct->id_parent = (isset($jsonModel->id_parent)) ? $jsonModel->id_parent : null;
            $QAModelTemplateCategoryStruct->category_label = $category->label;
            $QAModelTemplateCategoryStruct->code  = $category->code;

            foreach ($category->severities as $index2 => $severity){
                $severityModel = (!empty($QAModelTemplateCategoryStruct->severities[$index2])) ? $QAModelTemplateCategoryStruct->severities[$index2] : new QAModelTemplateSeverityStruct();
                $severityModel->id_category = (isset($category->id)) ? $category->id : null;
                $severityModel->severity_label = $severity->label;
                $severityModel->code  = $severity->code;
                $severityModel->penalty  = $severity->penalty;
                $severityModel->dqf_id  = (isset($severity->dqf_id)) ? $severity->dqf_id : null;

                $QAModelTemplateCategoryStruct->severities[$index2] = $severityModel;
            }

            $QAModelTemplateStruct->categories[$index] = $QAModelTemplateCategoryStruct;
        }

        return $QAModelTemplateStruct;
    }

    /**
     * @return array|mixed
     */
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