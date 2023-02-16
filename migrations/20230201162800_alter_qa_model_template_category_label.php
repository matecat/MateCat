<?php

class AlterQAModelTemplateCategoryLabel extends AbstractMatecatMigration {

    public $sql_up = [ '
        ALTER TABLE `qa_model_template_categories` 
CHANGE COLUMN `category_label` `category_label` VARCHAR(255) NOT NULL ;
    ' ];

    public $sql_down = [ '
        ALTER TABLE `qa_model_template_categories` 
CHANGE COLUMN `category_label` `category_label` VARCHAR(45) NOT NULL ;
    ' ];
}
