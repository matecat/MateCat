<?php

class CreateQaArchivedReports extends AbstractMatecatMigration
{

    public $sql_up = "
    CREATE TABLE `qa_archived_reports` (
          `id` INT NOT NULL AUTO_INCREMENT,
          `created_by` INT NOT NULL,
          `id_project` INT NOT NULL,

          `id_job` bigint(20) NOT NULL,
          `password` varchar(45) NOT NULL,

          `job_first_segment` bigint(20) unsigned NOT NULL,
          `job_last_segment` bigint(20) unsigned NOT NULL,

          `create_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `quality_report` TEXT NOT NULL,

          `version` INT NOT NULL DEFAULT 0,

          PRIMARY KEY (`id`),

          KEY `id_job_password_idx` (
            `id_job` ASC,
            `password` ASC,
            `job_first_segment` ASC,
            `job_last_segment` ASC
          )
    );
    ";

    public $sql_down = "DROP TABLE `qa_archived_reports`;";
}
