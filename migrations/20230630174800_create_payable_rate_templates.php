<?php

class CreatePayableRateTemplates extends AbstractMatecatMigration {

    public $sql_up = [ '
        CREATE TABLE IF NOT EXISTS `payable_rate_templates` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `uid` BIGINT(20) UNSIGNED NOT NULL,
          `version` INT(11) UNSIGNED NOT NULL DEFAULT 1,
          `name` VARCHAR(255) NOT NULL,
          `breakdowns` TEXT NOT NULL,
          `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
          `modified_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE INDEX `uid_name_idx` (`uid` ASC, `name` ASC));
    ' ];

    public $sql_down = [ '
        DROP TABLE `payable_rate_templates`;
    ' ];
}


