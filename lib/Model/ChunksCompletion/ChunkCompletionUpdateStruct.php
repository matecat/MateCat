<?php

namespace Model\ChunksCompletion;

use Model\DataAccess\AbstractDaoSilentStruct;

class ChunkCompletionUpdateStruct extends AbstractDaoSilentStruct
{

    public ?int $id = null;
    public int $id_project;
    public int $id_job;
    public string $password;
    public ?int $uid = null;
    public string $source;
    public int $job_first_segment;
    public int $job_last_segment;
    public string $create_date;
    public string $last_update;
    public string $last_translation_at;
    public bool $is_review;

}
