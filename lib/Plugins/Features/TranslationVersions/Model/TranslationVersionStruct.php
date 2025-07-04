<?php

namespace Features\TranslationVersions\Model;

class TranslationVersionStruct extends \Model\DataAccess\AbstractDaoSilentStruct implements \Model\DataAccess\IDaoStruct {

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
