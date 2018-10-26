<?php

class UpdateTranslationVersionsNewAndOldStatus extends AbstractMatecatMigration
{
    public $sql_up = [
            "ALTER TABLE `segment_translation_versions` ADD COLUMN old_status int(11) default null ",
            "ALTER TABLE `segment_translation_versions` ADD COLUMN new_status int(11) default null ",
    ] ;

    public $sql_down = [
            "ALTER TABLE `segment_translation_versions` DROP COLUMN old_status",
            "ALTER TABLE `segment_translation_versions` DROP COLUMN new_status"
    ];
}
