<?php

class DropDqfSegments extends AbstractMatecatMigration {

    public $sql_up = [ '
        DROP TABLE `dqf_segments`;
    ' ];

    public $sql_down = [ '
        CREATE TABLE `dqf_segments` (
          `id_segment` bigint(20) NOT NULL,
          `dqf_segment_id` bigint(20) DEFAULT NULL,
          `dqf_translation_id` bigint(20) DEFAULT NULL,
          UNIQUE KEY `dqf_segment_key` (`id_segment`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ' ];
}
