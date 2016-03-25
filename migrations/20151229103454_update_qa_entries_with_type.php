<?php

use Phinx\Migration\AbstractMigration;

class UpdateQaEntriesWithType extends AbstractMigration
{
    public $sql_up = <<<EOF
ALTER TABLE `qa_models`
    ADD COLUMN `pass_type` varchar(255) ,
    ADD COLUMN `pass_options` text
;
EOF;

    public $sql_down = <<<EOF
ALTER TABLE `qa_models`
    DROP COLUMN `pass_type` ,
    DROP COLUMN `pass_options` ;
EOF;

    public function up() {
        $this->execute($this->sql_up);
    }

    public function down() {
        $this->execute($this->sql_down);
    }

}
