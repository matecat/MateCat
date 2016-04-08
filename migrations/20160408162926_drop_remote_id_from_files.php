<?php

class DropRemoteIdFromFiles extends AbstractMatecatMigration {
    public $sql_up =  "ALTER TABLE `files` DROP COLUMN `remote_id`;";

    public $sql_down = "ALTER TABLE `files` ADD COLUMN `remote_id` text;";
}
