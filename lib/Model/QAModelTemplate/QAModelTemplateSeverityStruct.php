<?php

namespace QAModelTemplate;

use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;

class QAModelTemplateSeverityStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct, \JsonSerializable
{
    public $id;
    public $id_category;
    public $severity_label;
    public $penalty;
    public $sort;

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'id_category' => $this->id_category,
            'label' => $this->severity_label,
            'penalty' => (int)$this->penalty,
            'sort' => $this->sort,
        ];
    }
}