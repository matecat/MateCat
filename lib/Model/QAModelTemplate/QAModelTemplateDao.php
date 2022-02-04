<?php

namespace QAModelTemplate;

use DataAccess_AbstractDao;

class QAModelTemplateDao extends DataAccess_AbstractDao {

    const TABLE = "qa_model_templates";

    public static function getAll()
    {}

    public static function getByUser($uid)
    {}

    public static function get($id)
    {}

    public static function save(QAModelTemplateStruct $modelTemplateStruct)
    {}
}