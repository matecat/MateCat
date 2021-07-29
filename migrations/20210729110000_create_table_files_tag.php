<?php

class CreateTableFilesTag extends AbstractMatecatMigration {

    public $sql_up = [ '
        CREATE TABLE IF NOT EXISTS `files_parts` (
          `id` bigint(20) NOT NULL AUTO_INCREMENT,
          `id_file` bigint(20) NOT NULL,
          `tag_key` varchar(45) NOT NULL,
          `tag_value` varchar(255) NOT NULL,
          PRIMARY KEY (`id`),
          KEY `id_file_idx` (`id_file`) USING BTREE
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
    ' ];

    public $sql_down = [ 'DROP TABLE `files_parts`;' ];

}