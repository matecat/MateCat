<?php

namespace QAModelTemplate;

use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;

class QAModelTemplatePassfailThresholdStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct
{
    public $id;
    public $id_passfail;
    public $passfail_label;
    public $passfail_value;
}