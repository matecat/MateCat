<?php

namespace Model\LQA\QAModelTemplate;

use JsonSerializable;
use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;

class QAModelTemplateSeverityStruct extends AbstractDaoSilentStruct implements IDaoStruct, JsonSerializable {
    public ?int   $id          = null;
    public ?int   $id_category = null;
    public string $severity_code;
    public string $severity_label;
    public ?float $penalty     = 0.0;
    public ?int   $sort        = null;

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array {
        return [
                'id'          => (int)$this->id,
                'id_category' => $this->id_category,
                'code'        => $this->severity_code,
                'label'       => $this->severity_label,
                'penalty'     => floatval( $this->penalty ),
                'sort'        => $this->sort ?: null,
        ];
    }
}
