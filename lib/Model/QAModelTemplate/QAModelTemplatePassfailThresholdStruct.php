<?php

namespace QAModelTemplate;

use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;

class QAModelTemplatePassfailThresholdStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct, \JsonSerializable
{
    public $id;
    public $id_passfail;
    public $passfail_label;
    public $passfail_value;

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return [
            'id' => (int)$this->id,
            'id_passfail' => (int)$this->id_passfail,
            'label' => $this->passfail_label,
            'value' => (int)$this->passfail_value,
        ];
    }
}