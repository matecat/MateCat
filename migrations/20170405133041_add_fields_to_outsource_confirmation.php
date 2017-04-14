<?php

class AddFieldsToOutsourceConfirmation extends AbstractMatecatMigration
{
    public $sql_up = "
        ALTER TABLE `outsource_confirmation` 
          ADD COLUMN `currency` VARCHAR(25) NOT NULL DEFAULT 'EUR' AFTER `delivery_date`,
          ADD COLUMN `price` FLOAT(11,2) NOT NULL DEFAULT 0 AFTER `currency`,
          ADD COLUMN `quote_pid` VARCHAR(36) NULL AFTER `price`,
          ALGORITHM=INPLACE, LOCK=NONE;
    ";

    public $sql_down = "
        ALTER TABLE `outsource_confirmation` 
          DROP COLUMN `currency`,
          DROP COLUMN `price`,
          DROP COLUMN `quote_pid`
          ;
    ";

}
