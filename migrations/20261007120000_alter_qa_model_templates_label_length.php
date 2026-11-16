<?php

use migrations\AbstractMatecatMigration;

/**
 * `label` was a varchar(45), MySQL Workbench's default width, never a decision: every other template
 * name is 255. No ALGORITHM clause: on a multibyte charset the widening crosses the 255-byte boundary
 * of the length prefix, which MySQL cannot do in place, and the charset differs per installation.
 */
class AlterQaModelTemplatesLabelLength extends AbstractMatecatMigration {

    public $sql_up = [
        "ALTER TABLE `qa_model_templates` MODIFY COLUMN `label` varchar(255) NOT NULL;",
    ];

    public $sql_down = [
        "ALTER TABLE `qa_model_templates` MODIFY COLUMN `label` varchar(45) NOT NULL;",
    ];

}
