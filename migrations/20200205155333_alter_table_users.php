<?php

class AlterTableUsers extends AbstractMatecatMigration {

    public $sql_up = <<<EOF
      ALTER TABLE users CHANGE COLUMN `pass` `pass` VARCHAR(255);
EOF;

    public $sql_down = <<<EOF
      ALTER TABLE users CHANGE COLUMN `pass` `pass` VARCHAR(50);
EOF;

}
