<?php

namespace QAModelTemplate;

use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;

class QAModelTemplateStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct
{
    public $id;
    public $uid;
    public $label;

    // [points_per_thousand]
    public $pass_type;


    public $tresholds = [];

    /**
     * @var QAModelTemplateCategoryStruct[]
     */
    public $categories = [];
}