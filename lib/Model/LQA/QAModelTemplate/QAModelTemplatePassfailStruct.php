<?php

namespace Model\LQA\QAModelTemplate;

use JsonSerializable;
use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;

class QAModelTemplatePassfailStruct extends AbstractDaoSilentStruct implements IDaoStruct, JsonSerializable
{
    public ?int $id = null;
    public ?int $id_template = null;
    public string $passfail_type;

    /**
     * @var QAModelTemplatePassfailThresholdStruct[]
     */
    public array $thresholds = [];

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => (int)$this->id,
            'id_template' => $this->id_template,
            'type' => $this->passfail_type,
            'thresholds' => $this->thresholds,
        ];
    }
}