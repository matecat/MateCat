<?php

use Phinx\Migration\AbstractMigration;

class OrganizationTimestamp extends AbstractMatecatMigration
{

    public $sql_up = array(
        "ALTER TABLE `matecat`.`organizations` CHANGE COLUMN `created_at` `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP "
    );

    public $sql_down = array(
        " ALTER TABLE `matecat`.`organizations` CHANGE COLUMN `created_at` `created_at` DATETIME NOT NULL  "
    );

}
