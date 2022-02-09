<?php

class CreateTableQaModelPassfailOptionTemplates extends AbstractMatecatMigration {

    public $sql_up = [ '
        CREATE TABLE IF NOT EXISTS `qa_model_template_passfail_options` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `id_passfail` int(11) NOT NULL,
          `passfail_label` varchar(45) NOT NULL,
          `passfail_value` varchar(45) NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
    ' ];

    public $sql_down = [ 'DROP TABLE `qa_model_template_passfail_options`;' ];
}
