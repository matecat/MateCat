<?php

class MatecatMultipleInstances extends AbstractMatecatMigration
{

    public $sql_up = "ALTER TABLE `projects` CHANGE COLUMN `for_debug` `instance_id` TINYINT(4), algorithm=INPLACE, lock=NONE";

    public $sql_down = "ALTER TABLE `projects` CHANGE COLUMN `instance_id` `for_debug` TINYINT(4), algorithm=INPLACE, lock=NONE";

}
