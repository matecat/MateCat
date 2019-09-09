<?php

class AddTimeToEditToTranslationVersions extends AbstractMatecatMigration {

    public $sql_up = [
            'ALTER TABLE segment_translation_versions ADD COLUMN  time_to_edit INT(11) NULL, algorithm=INPLACE, lock=NONE'
    ];

    public $sql_down = [
            'ALTER TABLE segment_translation_versions DROP COLUMN time_to_edit'
    ];
}
