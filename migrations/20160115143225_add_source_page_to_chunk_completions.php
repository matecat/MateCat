<?php

use Phinx\Migration\AbstractMigration;

class AddSourcePageToChunkCompletions extends AbstractMigration {

    public $sql_up = "ALTER TABLE `chunk_completion_events` ADD COLUMN `is_review` tinyint(1) NOT NULL; " ;
    public $sql_down = "ALTER TABLE `chunk_completion_events` DROP COLUMN `is_review`" ;

    public function up() {
        $this->execute($this->sql_up);
    }

    public function down() {
        $this->execute( $this->sql_down);
    }
}
