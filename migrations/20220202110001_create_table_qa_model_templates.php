<?php

class CreateTableQaModelTemplates extends AbstractMatecatMigration {

    public $sql_up = [ '
        CREATE TABLE IF NOT EXISTS `qa_model_templates` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `uid` bigint(20) NOT NULL,
          `version` int(11) NOT NULL,
          `label` varchar(45) NOT NULL,
            PRIMARY KEY (`id`),
            KEY `uid` (`uid`) USING BTREE
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
    ' ];

    public $sql_down = [ 'DROP TABLE `qa_model_templates`;' ];

}