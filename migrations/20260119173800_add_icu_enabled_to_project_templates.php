<?php

use migrations\AbstractMatecatMigration;

class AddIcuEnabledToProjectTemplates extends AbstractMatecatMigration {

    public $sql_up = [
        "ALTER TABLE `project_templates` add column `icu_enabled` tinyint(1) DEFAULT 0, ALGORITHM=INPLACE, LOCK=NONE;",
    ];

    
    public $sql_down = [
        'ALTER TABLE `project_templates` DROP COLUMN `icu_enabled`;',
    ];

}