<?php

class AlterQAModelSeverities extends AbstractMatecatMigration {

    public $sql_up = [ '
        ALTER TABLE `qa_model_template_severities` CHANGE COLUMN `penalty` `penalty` FLOAT(11,2) NOT NULL ;
    ' ];

    public $sql_down = [ '
        ALTER TABLE `qa_model_template_severities` CHANGE COLUMN `penalty` `penalty` INT(11) NOT NULL ;
    ' ];
}
