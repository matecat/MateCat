<?php

namespace Model\LQA\QAModelTemplate;

use Exception;
use InvalidArgumentException;
use JsonSerializable;
use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;
use Model\LQA\CategoryDao;
use Model\LQA\QAModelInterface;
use TypeError;
use Utils\Date\DateTimeUtil;
use Utils\Validation\UserSuppliedName;

class QAModelTemplateStruct extends AbstractDaoSilentStruct implements IDaoStruct, JsonSerializable, QAModelInterface
{
    public int $id = 0;
    public int $uid = 0;
    public string $label = "";
    public int $version = 0;
    public ?string $created_at = null;
    public ?string $modified_at = null;
    public ?string $deleted_at = null;

    /**
     * @var ?QAModelTemplatePassfailStruct
     */
    public ?QAModelTemplatePassfailStruct $passfail = null;

    /**
     * @var QAModelTemplateCategoryStruct[]
     */
    public array $categories = [];

    /**
     * @param $json
     *
     * @return QAModelTemplateStruct
     * @throws Exception
     * @throws TypeError
     * @throws InvalidArgumentException when the name is empty, holds a character the connection
     *                                  cannot carry, or will not fit the column
     */
    public function hydrateFromJSON(string $json): QAModelTemplateStruct
    {
        $json = json_decode($json);

        if (!isset($json->model)) {
            throw new Exception("Cannot instantiate a new QAModelTemplateStruct. Invalid JSON provided.");
        }

        $jsonModel = $json->model;

        // QAModelTemplateStruct
        $QAModelTemplateStruct = $this;
        $QAModelTemplateStruct->version = $jsonModel->version;
        // Here rather than in the controller, so create and update are covered by one call: both
        // hydrate through this method. Unlike its siblings, `qa_model_templates` has a plain
        // KEY (uid) and no name column at all, so this is about the value being stored as one
        // spelling rather than about an index seeing a clash — and its `label` is a varchar(45),
        // not the 255 the other template names get.
        $QAModelTemplateStruct->label = UserSuppliedName::validated(
            $jsonModel->label,
            'label',
            UserSuppliedName::QA_MODEL_LABEL_MAX_LENGTH
        );
        $QAModelTemplateStruct->categories = [];

        // QAModelTemplatePassfailStruct
        $QAModelTemplatePassfailStruct = new QAModelTemplatePassfailStruct();
        $QAModelTemplatePassfailStruct->passfail_type = $jsonModel->passfail->type;
        $QAModelTemplatePassfailStruct->id_template = (isset($jsonModel->id)) ? $jsonModel->id : null;

        foreach ($jsonModel->passfail->thresholds as $index => $threshold) {
            $modelTemplatePassfailThresholdStruct = new QAModelTemplatePassfailThresholdStruct();
            $modelTemplatePassfailThresholdStruct->passfail_label = $threshold->label;
            $modelTemplatePassfailThresholdStruct->passfail_value = $threshold->value;

            $QAModelTemplatePassfailStruct->thresholds[$index] = $modelTemplatePassfailThresholdStruct;
        }

        $QAModelTemplateStruct->passfail = $QAModelTemplatePassfailStruct;

        // QAModelTemplateCategoryStruct[]
        foreach ($jsonModel->categories as $index => $category) {
            $QAModelTemplateCategoryStruct = new QAModelTemplateCategoryStruct();
            $QAModelTemplateCategoryStruct->id_template = ($QAModelTemplateStruct->id !== 0) ? $QAModelTemplateStruct->id : null;
            $QAModelTemplateCategoryStruct->id_parent = (isset($jsonModel->id_parent)) ? $jsonModel->id_parent : null;
            $QAModelTemplateCategoryStruct->category_label = $category->label;
            $QAModelTemplateCategoryStruct->code = $category->code;

            if ($category->sort) {
                $QAModelTemplateCategoryStruct->sort = $category->sort;
            }

            foreach ($category->severities as $index2 => $severity) {
                $severityModel = (!empty($QAModelTemplateCategoryStruct->severities[$index2])) ? $QAModelTemplateCategoryStruct->severities[$index2] : new QAModelTemplateSeverityStruct();
                $severityModel->id_category = (isset($category->id)) ? $category->id : null;
                $severityModel->severity_label = $severity->label;
                $severityModel->severity_code = $severity->code;
                $severityModel->penalty = $severity->penalty;

                if ($severity->sort) {
                    $severityModel->sort = $severity->sort;
                }

                $QAModelTemplateCategoryStruct->severities[$index2] = $severityModel;
            }

            $QAModelTemplateStruct->categories[$index] = $QAModelTemplateCategoryStruct;
        }

        return $QAModelTemplateStruct;
    }

    /**
     * @return array<string, mixed>
     * @throws \RuntimeException
     */
    public function getDecodedModel(CategoryDao $dao): array
    {
        $categoriesArray = [];
        $limitsArray = [];

        $passfail = $this->passfail ?? throw new \RuntimeException('Passfail must be set before calling getDecodedModel');
        foreach ($passfail->thresholds as $threshold) {
            if ($threshold->passfail_label === 'T') {
                $index = 0;
            } elseif ($threshold->passfail_label === 'R1') {
                $index = 1;
            } elseif ($threshold->passfail_label === 'R2') {
                $index = 2;
            }

            if (isset($index)) {
                $limitsArray[$index] = $threshold->passfail_value;
            }
        }

        foreach ($this->categories as $categoryStruct) {
            $category = [];
            $category['id'] = (int)$categoryStruct->id;
            $category['label'] = $categoryStruct->category_label;
            $category['code'] = $categoryStruct->code;
            $category['sort'] = $categoryStruct->sort ?: null;
            $category['severities'] = [];

            foreach ($categoryStruct->severities as $severityStruct) {
                $category['severities'][] = [
                    'id' => (int)$severityStruct->id,
                    'label' => $severityStruct->severity_label,
                    'code' => $severityStruct->severity_code,
                    'penalty' => floatval($severityStruct->penalty),
                    'sort' => $severityStruct->sort ?: null,
                ];
            }

            $categoriesArray[] = $category;
        }

        return [
            'model' => [
                "uid" => $this->uid,
                "id_template" => $this->id,
                "version" => $this->version,
                "label" => $this->label,
                "categories" => $categoriesArray,
                "passfail" => [
                    'type' => $passfail->passfail_type,
                    'options' => [
                        'limit' => $limitsArray
                    ]
                ],
            ]
        ];
    }

    /**
     * @return array<string, mixed>
     * @throws Exception
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'uid' => $this->uid,
            'label' => $this->label,
            'version' => $this->version,
            'categories' => $this->categories,
            'passfail' => $this->passfail,
            'createdAt' => DateTimeUtil::formatIsoDate($this->created_at),
            'modifiedAt' => DateTimeUtil::formatIsoDate($this->modified_at),
            'deletedAt' => DateTimeUtil::formatIsoDate($this->deleted_at),
        ];
    }
}
