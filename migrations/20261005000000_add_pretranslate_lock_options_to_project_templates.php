<?php

use migrations\AbstractMatecatMigration;

class AddPretranslateLockOptionsToProjectTemplates extends AbstractMatecatMigration {

    public $sql_up = [
        "ALTER TABLE `project_templates` ADD COLUMN `pretranslate_101_lock` TINYINT(1) NOT NULL DEFAULT 1, ADD COLUMN `pretranslate_100_lock` TINYINT(1) NOT NULL DEFAULT 0, ALGORITHM=INPLACE, LOCK=NONE;",
    ];

    public $sql_down = [
        "ALTER TABLE `project_templates` DROP COLUMN `pretranslate_101_lock`, DROP COLUMN `pretranslate_100_lock`;",
    ];

}
