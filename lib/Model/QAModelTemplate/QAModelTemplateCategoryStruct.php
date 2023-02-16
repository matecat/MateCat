<?php

namespace QAModelTemplate;

use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;

class QAModelTemplateCategoryStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct, \JsonSerializable
{
    public $id;
    public $id_template;
    public $id_parent;
    public $category_label;
    public $code;
    public $sort;

    /**
     * @var QAModelTemplateSeverityStruct[]
     */
    public $severities = [];

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return [
            'id' => (int)$this->id,
            'id_template' => (int)$this->id_template,
            'id_parent' => (int)$this->id_parent,
            'label' => $this->category_label,
            'code' => $this->code,
            'sort' => $this->sort,
            'severities' => $this->severities,
        ];
    }
}