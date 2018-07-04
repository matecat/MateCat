<?php

class CreateRemoteFiles extends AbstractMatecatMigration {

    public $sql_up = <<<EOF
CREATE TABLE `remote_files` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_file` bigint(20) NOT NULL,
  `id_job` bigint(20) NOT NULL,
  `remote_id` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_file` (`id_file`) USING BTREE,
  KEY `id_job` (`id_job`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ;
EOF;

    public $sql_down = "DROP table `remote_files`;";
}
