<?php

class CreateTableQaModelTemplates extends AbstractMatecatMigration {

    public $sql_up = [ '
        CREATE TABLE IF NOT EXISTS `qa_model_templates` (
          `id` bigint(20) NOT NULL AUTO_INCREMENT,
          `uid` bigint(20) NOT NULL,
          `label` varchar(45) NOT NULL,
          `dqf_id` int(11) DEFAULT NULL,
          `pass_type` varchar(255) NOT NULL,
          `pass_options` varchar(255) NOT NULL,
            PRIMARY KEY (`id`),
            KEY `uid` (`uid`) USING BTREE
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
    ' ];

    public $sql_down = [ 'DROP TABLE `qa_model_templates`;' ];

}