<?php

namespace QAModelTemplate;

use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;

class QAModelTemplatePassfailStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct
{
    public $id;
    public $id_template;
    public $passfail_type;

    /**
     * @var QAModelTemplatePassfailThresholdStruct[]
     */
    public $thresholds = [];
}