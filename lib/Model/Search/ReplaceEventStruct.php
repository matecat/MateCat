<?php

namespace Model\Search;

use Model\DataAccess\ShapelessConcreteStruct;

class ReplaceEventStruct extends ShapelessConcreteStruct
{

    // DATABASE FIELDS
    public ?int $id = null;
    public string $replace_version;
    public int $id_job;
    public string $job_password;
    public int $id_segment;
    public ?int $segment_version = 0;
    public ?string $translation_before_replacement = null;
    public string $translation_after_replacement;
    public ?string $source = null;
    public string $target;
    public string $status;
    public string $replacement;
    public string $created_at = '';
}