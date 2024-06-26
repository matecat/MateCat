<?php

namespace QAModelTemplate;

use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;

class QAModelTemplateSeverityStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct, \JsonSerializable
{
    public $id;
    public $id_category;
    public $severity_code;
    public $severity_label;
    public $penalty;
    public $sort;

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return [
            'id' => (int)$this->id,
            'id_category' => (int)$this->id_category,
            'code' => $this->severity_code,
            'label' => $this->severity_label,
            'penalty' => floatval($this->penalty),
        ];
    }
}
