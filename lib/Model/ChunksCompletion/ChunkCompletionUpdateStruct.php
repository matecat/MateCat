<?php

namespace Model\ChunksCompletion;

use Model\DataAccess\AbstractDaoSilentStruct;

class ChunkCompletionUpdateStruct extends AbstractDaoSilentStruct {

    const SOURCE_MERGE = 'merge';
    const SOURCE_USER  = 'user';

    public $id;
    public $id_project;
    public $id_job;
    public $password;
    public $uid;
    public $source;
    public $job_first_segment;
    public $job_last_segment;
    public $create_date;
    public $last_update;
    public $last_translation_at;
    public $is_review;

}
