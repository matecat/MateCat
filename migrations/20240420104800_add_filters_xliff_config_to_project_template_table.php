<?php

class AddFiltersXliffConfigToProjectTemplateTable extends AbstractMatecatMigration {

    public $sql_up = [
        'ALTER TABLE `project_templates` ADD COLUMN `filters_template_id` INT(11) DEFAULT NULL AFTER `qa_model_template_id`;',
        'ALTER TABLE `project_templates` ADD COLUMN `xliff_config_template_id` INT(11) DEFAULT NULL AFTER `filters_template_id`;',
    ];

    public $sql_down = [
        'ALTER TABLE `project_templates` DROP COLUMN `filters_template_id` ;',
        'ALTER TABLE `project_templates` DROP COLUMN `xliff_config_template_id` ;',
    ];
}

