<?php

class OrganizationTimestamp extends AbstractMatecatMigration
{

    public $sql_up = array(
        "ALTER TABLE `organizations` CHANGE COLUMN `created_at` `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP "
    );

    public $sql_down = array(
        " ALTER TABLE `organizations` CHANGE COLUMN `created_at` `created_at` DATETIME NOT NULL  "
    );

}
