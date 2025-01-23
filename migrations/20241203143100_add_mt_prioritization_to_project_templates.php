<?php

class AddMtPrioritizationToProjectTemplates extends AbstractMatecatMigration {

    public $sql_up = [
        'ALTER TABLE `project_templates` add column `tm_prioritization` tinyint(1) NULL DEFAULT 0;',
    ];

    
    public $sql_down = [
            'ALTER TABLE `project_templates` DROP COLUMN `tm_prioritization`;',
    ];

}