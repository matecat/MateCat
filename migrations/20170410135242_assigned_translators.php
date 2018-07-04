<?php

class AssignedTranslators extends AbstractMatecatMigration
{

    public $sql_up = [
        "
            CREATE TABLE `jobs_translators` (
              `id_job` INT NOT NULL,
              `job_password` VARCHAR(45) NOT NULL,
              `id_translator_profile` INT NULL COMMENT 'This value can be NULL because the translator can be anonymous',
              `added_by` INT NOT NULL,
              `email` VARCHAR(45) NOT NULL,
              `delivery_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `source` VARCHAR(10) NOT NULL,
              `target` VARCHAR(10) NOT NULL,
              PRIMARY KEY (`id_job`, `job_password`),
              INDEX `id_translator_idx` (`id_translator_profile` ASC),
              INDEX `added_by_idx` (`added_by` ASC)
            );   
        ",

        /* This table will contain only registered translators as UID matches the Users table*/
        "        
            CREATE TABLE `translator_profiles` (
              `id` INT NOT NULL AUTO_INCREMENT,
              `uid_translator` INT NOT NULL,
              `is_revision` TINYINT(1) NOT NULL DEFAULT 0,
              `translated_words` FLOAT(11,2) NOT NULL DEFAULT 0,
              `revised_words` FLOAT(11,2) NOT NULL DEFAULT 0,
              `source` VARCHAR(10) NOT NULL,
              `target` VARCHAR(10) NOT NULL,
              PRIMARY KEY `id_prk`( `id` ),
              UNIQUE `uid_src_trg_type_idx` ( `uid_translator` ASC, `source` ASC, `target` ASC, `is_revision` ASC ),
              INDEX `src_idx` (`source` ASC),
              INDEX `trg_idx` (`target` ASC),
              INDEX `src_trg_idx` (`source` ASC, `target` ASC)
            );
        "

    ];

    public $sql_down = [ "DROP TABLE `jobs_translators`;", "DROP TABLE `translator_profiles`;" ];

}
