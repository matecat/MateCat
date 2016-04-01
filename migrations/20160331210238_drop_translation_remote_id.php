<?php

class DropTranslationRemoteId extends AbstractMatecatMigration {
    public $sql_up = "ALTER TABLE `files` DROP COLUMN `translation_remote_id`";

    public $sql_down = "ALTER TABLE `files` ADD COLUMN `translation_remote_id` varchar(255);";
}
