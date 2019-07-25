<?php

namespace Search;

use DataAccess\ShapelessConcreteStruct;

class ReplaceEventStruct extends ShapelessConcreteStruct {

    // DATABASE FIELDS
    public $id;
    public $bulk_version;
    public $id_job;
    public $job_password;
    public $id_segment;
    public $segment_version;
    public $translation_before_replacement;
    public $translation_after_replacement;
    public $source;
    public $target;
    public $replacement;
    public $created_at;
}