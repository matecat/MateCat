<?php

namespace Plugins\Features\TranslationVersions\Model;

use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;

class TranslationVersionStruct extends AbstractDaoSilentStruct implements IDaoStruct {

    public ?int    $id              = null;
    public int     $id_segment;
    public int     $id_job;
    public string  $translation;
    public string  $creation_date;
    public int     $version_number  = 0;
    public ?int    $propagated_from = null;
    public ?int    $time_to_edit    = null;
    public ?string $raw_diff        = null;
    public ?int    $old_status      = null;
    public ?int    $new_status      = null;

}
