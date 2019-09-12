<?php

class CreateTableSegmentTranslationEvents extends AbstractMatecatMigration
{

    public $sql_up = <<<EOF
    CREATE TABLE `segment_translation_events` (

      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `id_job` BIGINT(20)  NOT NULL,
      `id_segment` BIGINT(20) NOT NULL,
      `uid` BIGINT(20) NOT NULL,
      `version_number` INT(11) NOT NULL,
      `source_page` TINYINT(4) NOT NULL,
      `status` VARCHAR(45) NOT NULL,

      PRIMARY KEY (`id`, `id_job` ),
      KEY `id_job` ( `id_job` ) USING BTREE,
      KEY `id_segment` ( `id_segment` )  USING BTREE
    )

    ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
EOF;


    public $sql_down = "DROP TABLE `segment_translation_events` ";
}
