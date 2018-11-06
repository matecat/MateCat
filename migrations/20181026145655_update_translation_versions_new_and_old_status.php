<?php

class UpdateTranslationVersionsNewAndOldStatus extends AbstractMatecatMigration {
    public $sql_up = [
            "ALTER TABLE `segment_translation_versions` ADD COLUMN old_status INT(11) DEFAULT NULL, ADD COLUMN new_status INT(11) DEFAULT NULL, algorithm=INPLACE, lock=NONE"
    ];

    public $sql_down = [
            "ALTER TABLE `segment_translation_versions` DROP COLUMN old_status, DROP COLUMN new_status, algorithm=INPLACE, lock=NONE"
    ];
}
