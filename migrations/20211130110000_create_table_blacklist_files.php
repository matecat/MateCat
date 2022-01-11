<?php

class CreateTableBlacklistFiles extends AbstractMatecatMigration {

    public $sql_up = [ '
        CREATE TABLE IF NOT EXISTS `blacklist_files` (
          `id` bigint(20) NOT NULL AUTO_INCREMENT,
          `id_job` bigint(20) NOT NULL,
          `password` varchar(45) NOT NULL,
          `file_path` varchar(255) NOT NULL,
          `file_name` varchar(255) NOT NULL,
          `target` VARCHAR(10) NOT NULL,
          `uid` bigint(20) NOT NULL,
            PRIMARY KEY (`id`),
            KEY `uid` (`uid`) USING BTREE,
            KEY `id_job` (`id_job`) USING BTREE,
            UNIQUE KEY `id_job_password` (`id_job`, `password`)
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
    ' ];

    public $sql_down = [ 'DROP TABLE `blacklist_files`;' ];

}