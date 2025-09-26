<?php

use migrations\AbstractMatecatMigration;

class AddSubfilteringToProjectTemplates extends AbstractMatecatMigration {

    public $sql_up = [
        'ALTER TABLE `project_templates` add column `subfiltering_handlers` varchar(50) DEFAULT NULL;',
    ];

    
    public $sql_down = [
        'ALTER TABLE `project_templates` DROP COLUMN `subfiltering_handlers`;',
    ];

}