<?php

class AddIsOriginalToRemoteFiles extends AbstractMatecatMigration {
    public $sql_up = "ALTER TABLE `remote_files` ADD COLUMN `is_original` tinyint(1) DEFAULT 0;";

    public $sql_down = "ALTER TABLE `remote_files` DROP COLUMN `is_original`;";
}
