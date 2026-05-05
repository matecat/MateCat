<?php

namespace Model\LQA;

use Exception;
use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;

class ModelStruct extends AbstractDaoSilentStruct implements IDaoStruct, QAModelInterface
{

    public ?int $id = null;
    public string $label;
    public string $create_date;
    public string $pass_type;
    public string $pass_options;

    public string $hash;

    public ?int $qa_model_template_id = null;
    public ?int $uid = null; // nullable for backward compatibility

    /**
     * Returns the serialized representation of categories and subcategories.
     *
     * @return array
     */
    public function getSerializedCategories(): array
    {
        return ['categories' => CategoryDao::getCategoriesAndSeverities($this->id)];
    }

    public function getCategoriesAndSeverities(): array
    {
        return CategoryDao::getCategoriesAndSeverities($this->id);
    }

    /**
     * @return CategoryStruct[]
     */
    public function getCategories(): array
    {
        return CategoryDao::getCategoriesByModel($this);
    }

    /**
     * @return mixed
     */
    public function getPassOptions(): mixed
    {
        return json_decode($this->pass_options);
    }

    /**
     * @return int[]
     * @throws Exception
     */
    public function getLimit(): array
    {
        $options = json_decode($this->pass_options, true);

        if (!array_key_exists('limit', $options)) {
            throw new Exception('limit is not defined in JSON options');
        }

        return $this->normalizeLimits($options['limit']);
    }

    /**
     * This function normalizes the limits.
     *
     * Ex: {"limit":{"1":"8","2":"5"}} is normalized to [0 => 8, 1 => 5]
     *
     * @param array $limits
     *
     * @return array
     */
    private function normalizeLimits(array $limits): array
    {
        $normalized = [];

        foreach ($limits as $limit) {
            $normalized[] = (int)$limit;
        }

        return $normalized;
    }

    /**
     * @return array
     */
    public function getDecodedModel(): array
    {
        $categoriesArray = [];
        foreach ($this->getCategories() as $categoryStruct) {
            $category = $categoryStruct->toArrayWithJsonDecoded();

            if (!empty($category)) {
                $categoriesArray[] = [
                    'id' => (int)$category['id'],
                    'label' => $category['label'],
                    'code' => ($category['options']['code'] ?? null),
                    'severities' => $category['severities'],
                ];
            }
        }

        return [
            'model' => [
                "id" => (int)$this->id,
                "uid" => $this->uid,
                "template_model_id" => $this->qa_model_template_id ? (int)$this->qa_model_template_id : null,
                "version" => 1,
                "label" => $this->label,
                "create_date" => $this->create_date,
                "categories" => $categoriesArray,
                "passfail" => [
                    'type' => $this->pass_type,
                    'options' => $this->getPassOptions()
                ],
            ]
        ];
    }
}
