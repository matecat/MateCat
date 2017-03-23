<?php


class OutsourceConfirmation extends AbstractMatecatMigration {

    public $sql_up = "
        CREATE TABLE `outsource_confirmation` (
          `id` INT NOT NULL AUTO_INCREMENT,
          `id_job` INT NOT NULL,
          `password` VARCHAR(45) NOT NULL,
          `id_vendor` INT NOT NULL DEFAULT 1,
          `vendor_name` VARCHAR(255) NOT NULL DEFAULT 'Translated',
          `create_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `delivery_date` DATETIME NOT NULL,
          PRIMARY KEY (`id`),
          UNIQUE INDEX `id_job_password_idx` (`id_job` ASC, `password` ASC)
      );
    ";

    public $sql_down = " DROP TABLE outsource_confirmation; ";

}
