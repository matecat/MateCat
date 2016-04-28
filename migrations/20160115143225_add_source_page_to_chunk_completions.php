<?php

class AddSourcePageToChunkCompletions extends AbstractMatecatMigration {

    public $sql_up = "ALTER TABLE `chunk_completion_events` ADD COLUMN `is_review` tinyint(1) NOT NULL; " ;
    public $sql_down = "ALTER TABLE `chunk_completion_events` DROP COLUMN `is_review`" ;
}
