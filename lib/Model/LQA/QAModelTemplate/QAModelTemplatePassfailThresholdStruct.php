<?php

namespace Model\LQA\QAModelTemplate;

use JsonSerializable;
use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;

class QAModelTemplatePassfailThresholdStruct extends AbstractDaoSilentStruct implements IDaoStruct, JsonSerializable {
    public ?int   $id             = null;
    public int    $id_passfail;
    public string $passfail_label;
    public ?float $passfail_value = 0.0;

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array {
        return [
                'id'          => (int)$this->id,
                'id_passfail' => $this->id_passfail,
                'label'       => $this->passfail_label,
                'value'       => (int)$this->passfail_value,
        ];
    }
}