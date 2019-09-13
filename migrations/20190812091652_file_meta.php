<?php

use Phinx\Migration\AbstractMigration;

class FileMeta extends AbstractMatecatMigration
{
    public $sql_up = <<<EOF
      ALTER TABLE `files` ADD COLUMN `is_converted` TINYINT NULL ;
EOF;

    public $sql_down = <<<EOF
      ALTER TABLE `files` DROP COLUMN `is_converted`;
EOF;

}
