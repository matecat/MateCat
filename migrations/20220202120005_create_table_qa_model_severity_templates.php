<?php

class CreateTableQaModelSeverityTemplates extends AbstractMatecatMigration {

    public $sql_up = [ '
        CREATE TABLE IF NOT EXISTS `qa_model_template_severities` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `id_category` int(11) NOT NULL,
          `severity_code` varchar(45) NOT NULL,
          `severity_label` varchar(45) NOT NULL,
          `penalty` int(11) NOT NULL,
          `dqf_id` int(11) DEFAULT NULL,
          `sort` int(11) DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
    ' ];

    public $sql_down = [ 'DROP TABLE `qa_model_template_severities`;' ];
}
