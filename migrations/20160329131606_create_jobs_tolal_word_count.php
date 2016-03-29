<?php

use Phinx\Migration\AbstractMigration;

class CreateJobsTolalWordCount extends AbstractMigration
{
    public $sql_up = <<<EOF
ALTER TABLE `jobs` ADD COLUMN `total_raw_wc` bigint(20) DEFAULT 1 ;
EOF;

    public $sql_down = <<<EOF
ALTER TABLE `jobs` DROP COLUMN `total_raw_wc` ;
EOF;


    public function up() {
        $this->execute($this->sql_up);
    }

    public function down() {
        $this->execute($this->sql_down);
    }

}
