<?php


class AlterJobChangeColumnIdJobToReviseToOnlyPrivate extends AbstractMatecatMigration {
    public $sql_up = " ALTER TABLE jobs CHANGE COLUMN id_job_to_revise only_private_tm INT(11) NOT NULL DEFAULT 0" ;
    public $sql_down = " ALTER TABLE jobs CHANGE COLUMN only_private_tm id_job_to_revise INT(11) NULL DEFAULT NULL " ;
}