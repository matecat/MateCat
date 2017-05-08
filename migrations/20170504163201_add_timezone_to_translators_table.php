<?php

class AddTimezoneToTranslatorsTable extends AbstractMatecatMigration {


    public $sql_up   = "
            ALTER TABLE `jobs_translators` 
                CHANGE COLUMN `delivery_date` `delivery_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ,
                ADD COLUMN `job_owner_timezone` DECIMAL(2,1) NOT NULL DEFAULT 0 AFTER `delivery_date`;
    ";

    public $sql_down = "
            ALTER TABLE `jobs_translators` 
                CHANGE COLUMN `delivery_date` `delivery_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ,
                DROP COLUMN `job_owner_timezone`;
    ";


}
