<?php

use migrations\AbstractMatecatMigration;

class AddMandatoryIssuesToProjectTemplates extends AbstractMatecatMigration {

    public $sql_up = [
        "ALTER TABLE `project_templates` ADD COLUMN `mandatory_issues` TEXT DEFAULT NULL, ALGORITHM=INPLACE, LOCK=NONE;",
    ];

    public $sql_down = [
        "ALTER TABLE `project_templates` DROP COLUMN `mandatory_issues`;",
    ];

}
