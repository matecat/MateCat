<?php

class CreateFiltersXliffConfigTemplateTable extends AbstractMatecatMigration {

    public $sql_up = [
        'CREATE TABLE IF NOT EXISTS `filters_xliff_config_templates` (
            `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `uid` bigint(20) NOT NULL,
            `xliff` TEXT DEFAULT NULL,
            `filters` TEXT DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `deleted_at` timestamp NULL DEFAULT NULL,
            `modified_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `uid_idx` (`uid`) USING BTREE);
        '
    ];

    public $sql_down = [
        'DROP TABLE `filters_xliff_config_templates`;'
    ];
}

