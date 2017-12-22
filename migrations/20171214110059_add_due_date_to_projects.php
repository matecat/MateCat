<?php

class AddDueDateToProjects extends AbstractMatecatMigration
{
	public $sql_up = " ALTER TABLE projects ADD COLUMN due_date DATETIME DEFAULT NULL, ALGORITHM=INPLACE,LOCK=NONE;" ;
	public $sql_down = " ALTER TABLE projects DROP COLUMN due_date" ;
}
