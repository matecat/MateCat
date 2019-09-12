<?php

class AddDeleteAtToQaEntries extends AbstractMatecatMigration
{
    public $sql_up = [ "ALTER TABLE qa_entries ADD COLUMN  deleted_at DATETIME DEFAULT NULL" ];

    public $sql_down = ['ALTER TABLE qa_entries DROP COLUMN deleted_at ' ] ;
}
