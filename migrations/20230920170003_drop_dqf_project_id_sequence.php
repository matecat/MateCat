<?php

class DropDqfProjectIdSequence extends AbstractMatecatMigration {

    public $sql_up = [ '
        ALTER TABLE sequences drop column id_dqf_project;
    ' ];

    public $sql_down = [ '
        ALTER TABLE sequences add column id_dqf_project bigint(20) unsigned NOT NULL;
    ' ];
}
