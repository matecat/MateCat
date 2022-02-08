<?php

namespace QAModelTemplate;

use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;

class QAModelTemplateCategoryStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct
{
    public $id;
    public $id_template;
    public $id_parent;
    public $category_label;
    public $code;
    public $dqf_id;
    public $sort;

    /**
     * @var QAModelTemplateSeverityStruct[]
     */
    public $severities = [];
}