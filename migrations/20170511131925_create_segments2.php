<?php

class CreateSegments2 extends AbstractMatecatMigration
{

    public $sql_up = "
    CREATE TABLE `segments2` (
      `id` bigint(20) NOT NULL AUTO_INCREMENT,
      `id_file` bigint(20) NOT NULL,
      `id_file_part` bigint(20) DEFAULT NULL,
      `internal_id` varchar(100) DEFAULT NULL,
      `xliff_mrk_id` varchar(70) DEFAULT NULL,
      `xliff_ext_prec_tags` text,
      `xliff_mrk_ext_prec_tags` text,
      `segment` text,
      `segment_hash` varchar(45) NOT NULL,
      `xliff_mrk_ext_succ_tags` text,
      `xliff_ext_succ_tags` text,
      `raw_word_count` double(20,2) DEFAULT NULL,
      `show_in_cattool` tinyint(4) DEFAULT '1',
      PRIMARY KEY (`id`),
      KEY `id_file` (`id_file`) USING BTREE,
      KEY `internal_id` (`internal_id`) USING BTREE,
      KEY `show_in_cat` (`show_in_cattool`) USING BTREE,
      KEY `raw_word_count` (`raw_word_count`) USING BTREE,
      KEY `segment_hash` (`segment_hash`) USING HASH COMMENT 'MD5 hash of segment content'
    ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;
";

    public $sql_down = "DROP TABLE `segments2` ";
}
