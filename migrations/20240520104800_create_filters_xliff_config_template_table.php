<?php

class CreateFiltersXliffConfigTemplateTable extends AbstractMatecatMigration {

    public $sql_up = [
        'CREATE TABLE IF NOT EXISTS `filters_xliff_config_templates` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) NOT NULL,
            `uid` bigint(20) NOT NULL,
            `xliff` TEXT DEFAULT NULL,
            `filters` TEXT DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `deleted_at` timestamp NULL DEFAULT NULL,
            `modified_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE INDEX `uid_name_idx` (`uid` ASC, `name` ASC));
        '
    ];

    public $sql_down = [
        'DROP TABLE `filters_xliff_config_templates`;'
    ];
}

