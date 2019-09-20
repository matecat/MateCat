<?php

class AddJobMetadata extends AbstractMatecatMigration  {

    public $sql_up = <<<EOF
CREATE TABLE `job_metadata` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `id_job` BIGINT(20) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `key` VARCHAR(255) NOT NULL, 
  `value` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_job_password` (`id_job`, `password`) USING BTREE,
  UNIQUE KEY `id_job_password_key` (`id_job`, `password`, `key`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
EOF;

    public $sql_down = <<<EOF
    DROP TABLE `job_metadata` ; 
EOF;

}
