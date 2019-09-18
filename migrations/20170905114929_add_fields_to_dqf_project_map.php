<?php

class AddFieldsToDqfProjectMap extends AbstractMatecatMigration
{
    public $sql_up = "ALTER TABLE dqf_child_projects_map ADD COLUMN project_type varchar(50), ADD COLUMN uid bigint(20)";
    public $sql_down = "ALTER TABLE dqf_child_projects_map DROP COLUMN project_type, DROP COLUMN uid";
}
