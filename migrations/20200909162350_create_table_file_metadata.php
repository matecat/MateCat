<?php

use Phinx\Migration\AbstractMigration;

class CreateTableFileMetadata extends AbstractMatecatMigration {
    public $sql_up = [ 'CREATE TABLE `file_metadata` (
          `id` bigint(20) NOT NULL AUTO_INCREMENT,
          `id_project` bigint(20) NOT NULL,
          `id_file` bigint(20) NOT NULL,
          `key` varchar(255) NOT NULL,
          `value` text NOT NULL,
          PRIMARY KEY (`id`),
          KEY `id_file_idx` (`id_file`),
          KEY `id_project_idx` (`id_project`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ' ];

    public $sql_down = [ 'DROP TABLE `file_metadata`' ];
}
