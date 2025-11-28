<?php

use migrations\AbstractMatecatMigration;

class AddSubfilteringToProjectTemplates extends AbstractMatecatMigration {

    public $sql_up = [
        "ALTER TABLE `project_templates` add column `subfiltering_handlers` varchar(1024) DEFAULT '[\"markup\", \"twig\", \"double_snail\", \"double_square\", \"double_percent\"]', ALGORITHM=INPLACE, LOCK=NONE;",
    ];

    
    public $sql_down = [
        'ALTER TABLE `project_templates` DROP COLUMN `subfiltering_handlers`;',
    ];

}