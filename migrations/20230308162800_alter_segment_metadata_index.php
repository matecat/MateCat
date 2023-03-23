<?php

class AlterSegmentMetadataIndex extends AbstractMatecatMigration {

    public $sql_up = [ '
        ALTER TABLE `segment_metadata` DROP PRIMARY KEY;
        ALTER TABLE `segment_metadata` ADD INDEX `idx_segment_metadata_id_segment_meta_key` (`id_segment`, `meta_key`) ;
    ' ];

    public $sql_down = [ '
        ALTER TABLE `segment_metadata` DROP INDEX `idx_segment_metadata_id_segment_meta_key`;
        ALTER TABLE `segment_metadata` ADD PRIMARY KEY(`id_segment`, `meta_key`);
    ' ];
}
