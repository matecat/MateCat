<?php

class AddCharacterCounterToProjectTemplateTable extends AbstractMatecatMigration {

    public $sql_up = [
        'ALTER TABLE `project_templates` add column `character_counter_mode` VARCHAR(36) NULL;',
        'ALTER TABLE `project_templates` add column `character_counter_count_tags` tinyint(1) NULL DEFAULT 0;',
    ];

    public $sql_down = [
        'ALTER TABLE `project_templates` DROP COLUMN `character_counter_mode` ;',
        'ALTER TABLE `project_templates` DROP COLUMN `character_counter_count_tags` ;',
    ];
}
