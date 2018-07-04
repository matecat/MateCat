<?php

class AddRebuttedAtToQaEntries extends AbstractMatecatMigration {
    public $sql_up = 'ALTER TABLE `qa_entries` ADD COLUMN `rebutted_at` DATETIME;';

    public $sql_down = 'ALTER TABLE `qa_entries` DROP COLUMN `rebutted_at`;';
}
