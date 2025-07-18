<?php

use migrations\AbstractMatecatMigration;

class CreateTableQaModelPassfailTemplates extends AbstractMatecatMigration {

    public $sql_up = [ '
        CREATE TABLE IF NOT EXISTS `qa_model_template_passfails` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `id_template` int(11) NOT NULL,
          `passfail_type` varchar(45) NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
    ' ];

    public $sql_down = [ 'DROP TABLE `qa_model_template_passfails`;' ];
}
