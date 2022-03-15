<?php

class CreateTableQaModelCategoryTemplates extends AbstractMatecatMigration {

    public $sql_up = [ '
        CREATE TABLE IF NOT EXISTS `qa_model_template_categories` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `id_template` int(11) NOT NULL,
          `id_parent` int(11) DEFAULT NULL,
          `category_label` varchar(45) NOT NULL,
          `code` varchar(45) NOT NULL,
          `sort` int(11) DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
    ' ];

    public $sql_down = [ 'DROP TABLE `qa_model_template_categories`;' ];
}
