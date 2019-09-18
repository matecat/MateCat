<?php

class CreateSegmentWarnings extends AbstractMatecatMigration {

    public $sql_up = <<<EOF
    CREATE TABLE `translation_warnings` ( 
     `id_job` bigint(20) NOT NULL,
     `id_segment` bigint(20) NOT NULL,
     `severity` tinyint(4) NOT NULL, 
     `scope` varchar(255) NOT NULL, 
     `data` text NOT NULL,
      KEY `id_job` (`id_job`) USING BTREE,
      KEY `id_segment` (`id_segment`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ;
EOF;

    public $sql_down = <<<EOF
    
    DROP TABLE `translation_warnings` ; 
    
EOF;

}
