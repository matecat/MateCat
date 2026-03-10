<?php

namespace Model\LQA\QAModelTemplate;

use JsonSerializable;
use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;

class QAModelTemplateCategoryStruct extends AbstractDaoSilentStruct implements IDaoStruct, JsonSerializable
{
    public ?int $id = null;
    public ?int $id_template = null;
    public ?int $id_parent = null;
    public string $category_label;
    public string $code;
    public ?int $sort = 0;

    /**
     * @var QAModelTemplateSeverityStruct[]
     */
    public array $severities = [];

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => (int)$this->id,
            'id_template' => (int)$this->id_template,
            'id_parent' => (int)$this->id_parent,
            'label' => $this->category_label,
            'code' => $this->code,
            'severities' => $this->severities,
            'sort' => $this->sort ?: null,
        ];
    }
}