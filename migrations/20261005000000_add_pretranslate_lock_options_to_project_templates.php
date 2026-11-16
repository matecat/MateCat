<?php

use migrations\AbstractMatecatMigration;

class AddPretranslateLockOptionsToProjectTemplates extends AbstractMatecatMigration {

    public $sql_up = [
        // Existing templates were created when a 100% match stayed translated and editable: they keep it.
        "ALTER TABLE `project_templates`
            ADD COLUMN `pretranslate_101_lock` TINYINT(1) NOT NULL DEFAULT 1,
            ADD COLUMN `pretranslate_100_lock` TINYINT(1) NOT NULL DEFAULT 0,
            ADD COLUMN `pretranslate_101_status` VARCHAR(16) NOT NULL DEFAULT 'APPROVED',
            ADD COLUMN `pretranslate_100_status` VARCHAR(16) NOT NULL DEFAULT 'TRANSLATED',
            ALGORITHM=INPLACE, LOCK=NONE;",
        // Templates created from now on approve and lock a 100% match.
        "ALTER TABLE `project_templates`
            ALTER COLUMN `pretranslate_100_lock` SET DEFAULT 1,
            ALTER COLUMN `pretranslate_100_status` SET DEFAULT 'APPROVED';",
    ];

    public $sql_down = [
        "ALTER TABLE `project_templates`
            DROP COLUMN `pretranslate_101_lock`,
            DROP COLUMN `pretranslate_100_lock`,
            DROP COLUMN `pretranslate_101_status`,
            DROP COLUMN `pretranslate_100_status`;",
    ];

}
