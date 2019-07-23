<?php

namespace Search;

use DataAccess\ShapelessConcreteStruct;

class ReplaceEventStruct extends ShapelessConcreteStruct {

    // EVENT TYPES
    const TYPE_REPLACE = 1;
    const TYPE_UNDO    = 2;
    const TYPE_REDO    = 3;

    // DATABASE FIELDS
    public $id;
    public $bulk_version;
    public $id_job;
    public $job_password;
    public $id_segment;
    public $segment_version;
    public $segment_before_replacement;
    public $segment_after_replacement;
    public $segment_words_delta;
    public $source;
    public $target;
    public $replacement;
    public $restored_from_bulk_version;
    public $type;
    public $created_at;
}