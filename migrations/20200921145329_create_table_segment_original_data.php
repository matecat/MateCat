<?php

use Phinx\Migration\AbstractMigration;

class CreateTableSegmentOriginalData extends AbstractMatecatMigration {
    public $sql_up = [ 'CREATE TABLE `segment_original_data` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `id_segment` int(11) NOT NULL,
          `map` text,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8
    ' ];

    public $sql_down = [ 'DROP TABLE `segment_original_data`' ];
}