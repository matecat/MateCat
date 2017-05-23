<?php

class AlterConfirmationOutsourcesToTimestamps extends AbstractMatecatMigration
{

    public $sql_up = [
            "ALTER TABLE `outsource_confirmation` 
                CHANGE COLUMN `create_date` `create_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ,
                CHANGE COLUMN `delivery_date` `delivery_date` TIMESTAMP NOT NULL ;",
            "ALTER TABLE `jobs_translators` CHANGE COLUMN `delivery_date` `delivery_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ;"
    ];

    public $sql_down = [
            "ALTER TABLE `outsource_confirmation` 
                CHANGE COLUMN `create_date` `create_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ,
                CHANGE COLUMN `delivery_date` `delivery_date` DATETIME NOT NULL ;",
            "ALTER TABLE `jobs_translators` CHANGE COLUMN `delivery_date` `delivery_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ;"
    ];

}