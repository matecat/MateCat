<?php

class AddDqfProjectIdSequence extends AbstractMatecatMigration {
    public $sql_up = "ALTER TABLE sequences add column id_dqf_project bigint(20) unsigned NOT NULL" ;
    public $sql_down = "ALTER TABLE sequences drop column id_dqf_project" ;
}
