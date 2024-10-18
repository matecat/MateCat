<?php

class AlterTemplateProjectTable extends AbstractMatecatMigration {

    public $sql_up = [
        'ALTER TABLE `project_templates` DROP COLUMN `speech2text`;',
        'ALTER TABLE `project_templates` DROP COLUMN `lexica`;',
        'ALTER TABLE `project_templates` DROP COLUMN `tag_projection`;',
        'ALTER TABLE `project_templates` DROP COLUMN `cross_language_matches`;',
        'ALTER TABLE `project_templates` add column `subject` VARCHAR(255) NULL DEFAULT NULL;',
        'ALTER TABLE `project_templates` add column `source_language` VARCHAR(45) NULL DEFAULT NULL;',
        'ALTER TABLE `project_templates` add column `target_language` VARCHAR(255) NULL DEFAULT NULL;',
    ];

    
    public $sql_down = [
        'ALTER TABLE `project_templates` add column `speech2text` TINYINT(1) NOT NULL DEFAULT  0;',
        'ALTER TABLE `project_templates` add column `lexica` TINYINT(1) NOT NULL DEFAULT  0;',
        'ALTER TABLE `project_templates` add column `tag_projection` TINYINT(1) NOT NULL DEFAULT  0;',
        'ALTER TABLE `project_templates` add column `cross_language_matches` TEXT DEFAULT NULL;',
        'ALTER TABLE `project_templates` DROP COLUMN `subject`;',
        'ALTER TABLE `project_templates` DROP COLUMN `source_language`;',
        'ALTER TABLE `project_templates` DROP COLUMN `target_language`;',
    ];
}
