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
    public $translation_words_delta;
    public $source;
    public $target;
    public $replacement;
    public $restored_from_bulk_version;
    public $type;
    public $created_at;
}