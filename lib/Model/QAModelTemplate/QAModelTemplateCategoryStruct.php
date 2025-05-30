<?php

namespace QAModelTemplate;

use \DataAccess\AbstractDaoSilentStruct;
use \DataAccess\IDaoStruct;

class QAModelTemplateCategoryStruct extends \DataAccess\AbstractDaoSilentStruct implements \DataAccess\IDaoStruct, \JsonSerializable {
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
    public function jsonSerialize() {
        return [
                'id'          => (int)$this->id,
                'id_template' => (int)$this->id_template,
                'id_parent'   => (int)$this->id_parent,
                'label'       => $this->category_label,
                'code'        => $this->code,
                'severities'  => $this->severities,
                'sort'        => $this->sort ? (int)$this->sort : null,
        ];
    }
}