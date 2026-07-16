<?php

use migrations\AbstractMatecatMigration;

class AlterIcuEnabledDefaultProjectTemplates extends AbstractMatecatMigration {

    public $sql_up = [
        "ALTER TABLE `project_templates` MODIFY COLUMN `icu_enabled` tinyint(1) DEFAULT 1, ALGORITHM=INPLACE, LOCK=NONE;",
    ];

    public $sql_down = [
        "ALTER TABLE `project_templates` MODIFY COLUMN `icu_enabled` tinyint(1) DEFAULT 0, ALGORITHM=INPLACE, LOCK=NONE;",
    ];

}
