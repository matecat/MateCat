<?php

use Phinx\Migration\AbstractMigration;

class CreateTableForDqfSegments extends AbstractMatecatMigration {

    public $sql_up = [
            "CREATE TABLE `dqf_segments` (
              id_segment bigint(20) NOT NULL,
              id_dqf_segment bigint( 20 ),
              UNIQUE KEY `dqf_segment_key` ( `id_segment` )
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    " ];

    public $sql_down = ['DROP TABLE `dqf_segments`'] ;
}
