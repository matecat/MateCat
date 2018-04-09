<?php

class AlterTableNoteForJson extends AbstractMatecatMigration {
    public $sql_up = " ALTER TABLE `segment_notes` ADD COLUMN `json` TEXT NULL DEFAULT NULL, ALGORITHM = INPLACE, LOCK=NONE " ;
    public $sql_down = " ALTER TABLE `segment_notes` DROP COLUMN  `json`, ALGORITHM = INPLACE, LOCK=NONE " ;
}
