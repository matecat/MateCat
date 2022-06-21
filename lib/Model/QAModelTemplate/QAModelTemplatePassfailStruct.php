<?php

namespace QAModelTemplate;

use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;

class QAModelTemplatePassfailStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct, \JsonSerializable
{
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
    public function jsonSerialize()
    {
        return [
            'id' => (int)$this->id,
            'id_template' => (int)$this->id_template,
            'type' => $this->passfail_type,
            'thresholds' => $this->thresholds,
        ];
    }
}