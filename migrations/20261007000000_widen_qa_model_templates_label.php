<?php

use migrations\AbstractMatecatMigration;

class WidenQaModelTemplatesLabel extends AbstractMatecatMigration {

    public $sql_up = [
        "ALTER TABLE `qa_model_templates` MODIFY COLUMN `label` VARCHAR(255) NOT NULL;",
    ];

    public $sql_down = [
        "ALTER TABLE `qa_model_templates` MODIFY COLUMN `label` VARCHAR(45) NOT NULL;",
    ];

}
