<?php

namespace QAModelTemplate;

use \DataAccess\AbstractDaoSilentStruct;
use \DataAccess\IDaoStruct;

class QAModelTemplatePassfailStruct extends \DataAccess\AbstractDaoSilentStruct implements \DataAccess\IDaoStruct, \JsonSerializable {
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