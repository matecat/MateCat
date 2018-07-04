<?php

class AddTranslationRemoteIdToFiles extends AbstractMatecatMigration {
    public $sql_up = "ALTER TABLE `files` ADD COLUMN `translation_remote_id` varchar(255); " ;
    public $sql_down = "ALTER TABLE `files` DROP COLUMN `translation_remote_id`" ;
}

