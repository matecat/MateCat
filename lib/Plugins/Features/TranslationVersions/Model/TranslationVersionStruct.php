<?php

namespace Features\TranslationVersions\Model;

class TranslationVersionStruct extends \DataAccess\AbstractDaoSilentStruct implements \DataAccess\IDaoStruct {

    public $id;
    public $id_segment;
    public $id_job;
    public $translation;
    public $creation_date;
    public $version_number;
    public $propagated_from;
    public $time_to_edit;

    public $raw_diff;

    public $old_status;
    public $new_status;

}
