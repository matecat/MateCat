<?php

class FixMetadataValue extends AbstractMatecatMigration {
    public $sql_up = " ALTER TABLE job_metadata MODIFY COLUMN  value TEXT NOT NULL " ;
    public $sql_down = " ALTER TABLE job_metadata MODIFY COLUMN  value VARCHAR(255) NOT NULL " ;
}
