<?php

use Phinx\Migration\AbstractMigration;

class FileMeta extends AbstractMigration
{
    public $sql_up = <<<EOF
      ALTER TABLE `files` ADD COLUMN `is_converted` TINYINT NULL AFTER `sha1_original_file`; 
      ALTER TABLE `files` ADD COLUMN `metadata` JSON NULL AFTER `is_converted`; 
EOF;

    public $sql_down = <<<EOF
      ALTER TABLE `files` DROP COLUMN `is_converted`; 
      ALTER TABLE `files` DROP COLUMN `metadata`;
EOF;

    public function up(){
        $this->execute($this->sql_up);
    }

    public function down(){
        $this->execute($this->sql_down);
    }
}
