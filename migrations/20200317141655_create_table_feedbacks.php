<?php

use Phinx\Migration\AbstractMigration;

class CreateTableFeedbacks extends AbstractMatecatMigration {
    public $sql_up = [ 'CREATE TABLE `revision_feedbacks` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `id_job` int(11) NOT NULL,
          `password` varchar(45) NOT NULL,
          `revision_number` int(1) NOT NULL,
          `feedback` text,
          PRIMARY KEY (`id`),
          UNIQUE KEY `job_unique_key` (`id_job`,`password`,`revision_number`)
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8
    ' ];

    public $sql_down = [ 'DROP TABLE `revision_feedbacks`' ];
}
