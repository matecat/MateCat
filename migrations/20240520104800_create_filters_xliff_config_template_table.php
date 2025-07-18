<?php

use migrations\AbstractMatecatMigration;

class CreateFiltersXliffConfigTemplateTable extends AbstractMatecatMigration {

    public $sql_up = [
        'CREATE TABLE IF NOT EXISTS `filters_config_templates` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) NOT NULL,
            `uid` bigint(20) NOT NULL,
            `json` TEXT DEFAULT NULL,
            `xml` TEXT DEFAULT NULL,
            `yaml` TEXT DEFAULT NULL,
            `ms_excel` TEXT DEFAULT NULL,
            `ms_word` TEXT DEFAULT NULL,
            `ms_powerpoint` TEXT DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `deleted_at` timestamp NULL DEFAULT NULL,
            `modified_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE INDEX `uid_name_idx` (`uid` ASC, `name` ASC));
        ',
        'CREATE TABLE IF NOT EXISTS `xliff_config_templates` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) NOT NULL,
            `uid` bigint(20) NOT NULL,
            `rules` TEXT DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `deleted_at` timestamp NULL DEFAULT NULL,
            `modified_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE INDEX `uid_name_idx` (`uid` ASC, `name` ASC));
        ',
    ];

    public $sql_down = [
        'DROP TABLE `filters_templates`;',
        'DROP TABLE `xliff_config_templates`;'
    ];
}

