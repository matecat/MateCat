<?php

use migrations\AbstractMatecatMigration;

class AddDitaToFiltersConfigTable extends AbstractMatecatMigration {

    public $sql_up = [
        'ALTER TABLE `filters_config_templates` ADD COLUMN `dita` TEXT DEFAULT "{}" AFTER `ms_powerpoint`;',
    ];

    public $sql_down = [
        'ALTER TABLE `filters_config_templates` DROP COLUMN `dita` ;',
    ];
}

