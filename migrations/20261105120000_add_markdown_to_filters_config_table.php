<?php

use migrations\AbstractMatecatMigration;

class AddMarkdownToFiltersConfigTable extends AbstractMatecatMigration {

    // No DEFAULT: MySQL 8 rejects a literal default on a TEXT column. Rows saved before this
    // migration keep NULL, and the struct reads that back as "no markdown settings", like dita.
    public $sql_up = [
        'ALTER TABLE `filters_config_templates` ADD COLUMN `markdown` TEXT DEFAULT NULL AFTER `dita`;',
    ];

    public $sql_down = [
        'ALTER TABLE `filters_config_templates` DROP COLUMN `markdown` ;',
    ];
}
