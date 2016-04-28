<?php

class CreateChunkCompletionUpdates extends AbstractMatecatMigration {

    public $sql_up = <<<EOF
CREATE TABLE `chunk_completion_updates` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_project` bigint(20) NOT NULL,
  `id_job` bigint(20) NOT NULL,
  `uid` bigint(20) DEFAULT NULL,
  `job_first_segment` bigint(20) unsigned NOT NULL,
  `job_last_segment` bigint(20) unsigned NOT NULL,
  `password` varchar(45) NOT NULL,
  `source` varchar(45) NOT NULL,
  `create_date` datetime NOT NULL ,
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_translation_at` datetime,
  `is_review` tinyint(1) NOT NULL,
  `remote_ip_address` varchar(45) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_project` (`id_project`) USING BTREE,
  KEY `id_job` (`id_job`) USING BTREE,
  KEY `create_date` (`create_date`) USING BTREE,
  UNIQUE KEY `unique_record` (`id_job`, `password`, `job_first_segment`, `job_last_segment`, `is_review`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

EOF;

    public $sql_down = "DROP table `chunk_completion_updates`";
}
