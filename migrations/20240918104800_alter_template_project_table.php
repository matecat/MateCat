<?php

class AlterTemplateProjectTable extends AbstractMatecatMigration {

    public $sql_up = [
        'ALTER TABLE `project_templates` add column `dictation` TINYINT(1) NULL DEFAULT 0;',
        'ALTER TABLE `project_templates` add column `show_whitespace` TINYINT(1) NULL DEFAULT 0;',
        'ALTER TABLE `project_templates` add column `character_counter` TINYINT(1) NULL DEFAULT 0;',
        'ALTER TABLE `project_templates` add column `ai_assistant` TINYINT(1) NOT NULL DEFAULT 0;',
        'ALTER TABLE `project_templates` add column `team_id` INT(11) NULL DEFAULT NULL;',
        'ALTER TABLE `project_templates` add column `subject` VARCHAR(255) NULL DEFAULT NULL;',
        'ALTER TABLE `project_templates` add column `source_language` VARCHAR(45) NULL DEFAULT NULL;',
        'ALTER TABLE `project_templates` add column `target_language` VARCHAR(255) NULL DEFAULT NULL;',
    ];

    public $sql_down = [
        'ALTER TABLE `project_templates` DROP COLUMN `dictation`;',
        'ALTER TABLE `project_templates` DROP COLUMN `show_whitespace`;',
        'ALTER TABLE `project_templates` DROP COLUMN `character_counter`;',
        'ALTER TABLE `project_templates` DROP COLUMN `ai_assistant`;',
        'ALTER TABLE `project_templates` DROP COLUMN `team_id`;',
        'ALTER TABLE `project_templates` DROP COLUMN `subject`;',
        'ALTER TABLE `project_templates` DROP COLUMN `source_language`;',
        'ALTER TABLE `project_templates` DROP COLUMN `target_language`;',
    ];
}
