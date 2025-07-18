<?php

namespace Model\LQA\QAModelTemplate;

use JsonSerializable;
use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;

class QAModelTemplatePassfailStruct extends AbstractDaoSilentStruct implements IDaoStruct, JsonSerializable {
    public $id;
    public $id_template;
    public $passfail_type;

    /**
     * @var QAModelTemplatePassfailThresholdStruct[]
     */
    public $thresholds = [];

    /**
     * @inheritDoc
     */
    public function jsonSerialize() {
        return [
                'id'          => (int)$this->id,
                'id_template' => (int)$this->id_template,
                'type'        => $this->passfail_type,
                'thresholds'  => $this->thresholds,
        ];
    }
}