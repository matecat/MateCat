<?php

namespace Search;

use DataAccess\ShapelessConcreteStruct;

class ReplaceEventStruct extends ShapelessConcreteStruct {
    public $id;
    public $id_job;
    public $job_password;
    public $id_segment;
    public $source;
    public $target;
    public $language;
    public $created_at;
}