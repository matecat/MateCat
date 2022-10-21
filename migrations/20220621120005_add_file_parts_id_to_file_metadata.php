<?php

class AddFilePartsIdToFileMetadata extends AbstractMatecatMigration {

    public $sql_up = [ '
        ALTER TABLE `file_metadata` ADD COLUMN `files_parts_id` INT(11) NULL AFTER `value`;
 ;
    ' ];

    public $sql_down = [ 'ALTER TABLE `file_metadata` DROP COLUMN `files_parts_id`;' ];
}
