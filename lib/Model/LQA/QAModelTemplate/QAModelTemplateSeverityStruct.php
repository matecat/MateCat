<?php

namespace Model\LQA\QAModelTemplate;

use JsonSerializable;
use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;

class QAModelTemplateSeverityStruct extends AbstractDaoSilentStruct implements IDaoStruct, JsonSerializable {
    public $id;
    public $id_category;
    public $severity_code;
    public $severity_label;
    public $penalty;
    public $sort;

    /**
     * @inheritDoc
     */
    public function jsonSerialize() {
        return [
                'id'          => (int)$this->id,
                'id_category' => (int)$this->id_category,
                'code'        => $this->severity_code,
                'label'       => $this->severity_label,
                'penalty'     => floatval( $this->penalty ),
                'sort'        => $this->sort ? (int)$this->sort : null,
        ];
    }
}
