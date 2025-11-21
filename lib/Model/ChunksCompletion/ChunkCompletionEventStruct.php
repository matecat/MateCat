<?php

namespace Model\ChunksCompletion;

use Model\DataAccess\AbstractDaoSilentStruct;

class ChunkCompletionEventStruct extends AbstractDaoSilentStruct
{

    const string SOURCE_MERGE = 'merge';
    const string SOURCE_USER  = 'user';

    public ?int   $id  = null;
    public int    $id_project;
    public int    $id_job;
    public string $password;
    public ?int   $uid = null; //nullable for anonymous users. Backward compatibility
    public string $source;
    public int    $job_first_segment;
    public int    $job_last_segment;
    public string $create_date;
    public bool   $is_review;

}
