<?php

use migrations\AbstractMatecatMigration;

class AddPretranslateLockOptionsToProjectTemplates extends AbstractMatecatMigration {

    public $sql_up = [
        "ALTER TABLE `project_templates`
            ADD COLUMN `pretranslate_101_lock` TINYINT(1) NOT NULL DEFAULT 1,
            ADD COLUMN `pretranslate_100_lock` TINYINT(1) NOT NULL DEFAULT 0,
            ADD COLUMN `pretranslate_101_status` VARCHAR(16) NOT NULL DEFAULT 'APPROVED',
            ADD COLUMN `pretranslate_100_status` VARCHAR(16) NOT NULL DEFAULT 'TRANSLATED',
            ALGORITHM=INPLACE, LOCK=NONE;",
    ];

    public $sql_down = [
        "ALTER TABLE `project_templates`
            DROP COLUMN `pretranslate_101_lock`,
            DROP COLUMN `pretranslate_100_lock`,
            DROP COLUMN `pretranslate_101_status`,
            DROP COLUMN `pretranslate_100_status`;",
    ];

}
