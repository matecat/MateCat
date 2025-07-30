<?php

class CreateJobCustomPayableRatesTable extends AbstractMatecatMigration {

    public $sql_up = [ '
        CREATE TABLE IF NOT EXISTS `job_custom_payable_rates` (
            `id_job` INT(11) NOT NULL,
            `custom_payable_rate_model_id` INT(11) NOT NULL,
            `custom_payable_rate_model_name` VARCHAR(255) NOT NULL,
            `custom_payable_rate_model_version` INT(11) NOT NULL,
            UNIQUE INDEX `id_job_UNIQUE` (`id_job` ASC));
    ' ];

    public $sql_down = [ '
        DROP TABLE `job_custom_payable_rates`;
    ' ];
}
