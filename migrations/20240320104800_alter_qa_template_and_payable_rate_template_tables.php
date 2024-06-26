<?php

class AlterQATemplateAndPayableRateTemplateTables extends AbstractMatecatMigration {

    public $sql_up = [
        'ALTER TABLE `project_templates` ADD INDEX `uid_idx` (`uid` ASC);',
        'ALTER TABLE `payable_rate_templates` ADD COLUMN `deleted_at` TIMESTAMP NULL AFTER `modified_at`;',
        'ALTER TABLE `qa_model_templates` 
            ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER `label`,
            ADD COLUMN `modified_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER `created_at`,
            ADD COLUMN `deleted_at` TIMESTAMP NULL AFTER `modified_at`;
        '
    ];

    public $sql_down = [
        'ALTER TABLE `project_templates` DROP INDEX `uid_idx` ;',
        'ALTER TABLE `payable_rate_templates` DROP COLUMN `deleted_at` ;',
        'ALTER TABLE `qa_model_templates` DROP COLUMN `created_at` ;',
        'ALTER TABLE `qa_model_templates` DROP COLUMN `modified_at` ;',
        'ALTER TABLE `qa_model_templates` DROP COLUMN `deleted_at` ;',
    ];
}

