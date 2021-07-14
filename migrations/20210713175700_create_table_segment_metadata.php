<?php

class CreateTableSegmentMetadata extends AbstractMatecatMigration {

    public $sql_up = [ '
        CREATE TABLE `segment_metadata` (
          `id_segment` bigint(20) NOT NULL,
          `meta_key` varchar(45) NOT NULL,
          `meta_value` varchar(255) NOT NULL,
          PRIMARY KEY (`id_segment`,`meta_key`),
          KEY `id_segment_idx` (`id_segment`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ' ];

    public $sql_down = [ 'DROP TABLE `segment_metadata`' ];

}