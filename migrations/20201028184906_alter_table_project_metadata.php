<?php

class AlterTableProjectMetadata extends AbstractMatecatMigration {

    public $sql_up = [ 'ALTER TABLE project_metadata CHANGE COLUMN value value VARCHAR(2048) NOT NULL' ];

    public $sql_down = [ 'ALTER TABLE project_metadata CHANGE COLUMN value value VARCHAR(255) NOT NULL' ];

}
