<?php

namespace QAModelTemplate;

use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;
use LQA\QAModelInterface;

class QAModelTemplateStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct, \JsonSerializable, QAModelInterface
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
        $QAModelTemplateStruct->passfail = null;
        $QAModelTemplateStruct->categories = [];

        // QAModelTemplatePassfailStruct
        $QAModelTemplatePassfailStruct = new QAModelTemplatePassfailStruct();
        $QAModelTemplatePassfailStruct->passfail_type = $jsonModel->passfail->type;
        $QAModelTemplatePassfailStruct->id_template = (isset($jsonModel->id)) ? $jsonModel->id : null;

        foreach ($jsonModel->passfail->thresholds as $index => $threshold){

            $modelTemplatePassfailThresholdStruct = new QAModelTemplatePassfailThresholdStruct();
            $modelTemplatePassfailThresholdStruct->passfail_label = $threshold->label;
            $modelTemplatePassfailThresholdStruct->passfail_value = $threshold->value;

            $QAModelTemplatePassfailStruct->thresholds[$index] = $modelTemplatePassfailThresholdStruct;
        }

        $QAModelTemplateStruct->passfail = $QAModelTemplatePassfailStruct;

        // QAModelTemplateCategoryStruct[]
        foreach ($jsonModel->categories as $index => $category){

            $QAModelTemplateCategoryStruct = new QAModelTemplateCategoryStruct();
            $QAModelTemplateCategoryStruct->id_template = (isset($QAModelTemplateStruct->id)) ? $QAModelTemplateStruct->id : null;
            $QAModelTemplateCategoryStruct->id_parent = (isset($jsonModel->id_parent)) ? $jsonModel->id_parent : null;
            $QAModelTemplateCategoryStruct->category_label = $category->label;
            $QAModelTemplateCategoryStruct->code  = $category->code;

            foreach ($category->severities as $index2 => $severity){
                $severityModel = (!empty($QAModelTemplateCategoryStruct->severities[$index2])) ? $QAModelTemplateCategoryStruct->severities[$index2] : new QAModelTemplateSeverityStruct();
                $severityModel->id_category = (isset($category->id)) ? $category->id : null;
                $severityModel->severity_label = $severity->label;
                $severityModel->severity_code  = $severity->code;
                $severityModel->penalty  = $severity->penalty;
                $severityModel->dqf_id  = (isset($severity->dqf_id)) ? $severity->dqf_id : null;

                $QAModelTemplateCategoryStruct->severities[$index2] = $severityModel;
            }

            $QAModelTemplateStruct->categories[$index] = $QAModelTemplateCategoryStruct;
        }

        return $QAModelTemplateStruct;
    }

    /**
     * @return array
     */
    public function getDecodedModel()
    {
        $categoriesArray = [];
        $limitsArray = [];

        foreach ($this->passfail->thresholds as $threshold){

            if($threshold->passfail_label === 'T'){
                $index = 0;
            } elseif ($threshold->passfail_label === 'R1'){
                $index = 1;
            } elseif ($threshold->passfail_label === 'R2'){
                $index = 2;
            }

            if(isset($index)){
                $limitsArray[$index] =$threshold->passfail_value;
            }
        }

        foreach ($this->categories as $categoryStruct){
            $category = [];
            $category['id'] = (int)$categoryStruct->id;
            $category['label'] = $categoryStruct->category_label;
            $category['code'] = $categoryStruct->code;
            $category['severities'] = [];

            foreach ($categoryStruct->severities as $severityStruct){
                $category['severities'][] = [
                        'id' => (int)$severityStruct->id,
                        'label' => $severityStruct->severity_label,
                        'code' => $severityStruct->severity_code,
                        'penalty' => floatval($severityStruct->penalty),
                ];
            }

            $categoriesArray[] = $category;
        }

        return [
                'model' => [
                        "id_template" => (int)$this->id,
                        "version" => (int)$this->version,
                        "label" => $this->label,
                        "categories" => $categoriesArray,
                        "passfail" => [
                                'type' => $this->passfail->passfail_type,
                                'options' => [
                                        'limit' => $limitsArray
                                ]
                        ],
                ]
        ];
    }

    /**
     * @return array|mixed
     */
    public function jsonSerialize()
    {
        return [
                'id' => (int)$this->id,
                'uid' => (int)$this->uid,
                'label' => $this->label,
                'version' => (int)$this->version,
                'categories' => $this->categories,
                'passfail' => $this->passfail,
        ];
    }
}
