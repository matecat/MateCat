<?php

use migrations\AbstractMatecatMigration;

class AddDialectStrictToProjectTemplates extends AbstractMatecatMigration {

    public $sql_up = [
        'ALTER TABLE `project_templates` add column `dialect_strict` tinyint(1) NULL DEFAULT 0;',
    ];

    
    public $sql_down = [
        'ALTER TABLE `project_templates` DROP COLUMN `dialect_strict`;',
    ];

}