<?php

use Phinx\Migration\AbstractMigration;

class AddGoogleAccessTokenToUsers extends AbstractMigration { 
    public $sql_up = "ALTER TABLE `users` ADD COLUMN `oauth_access_token` TEXT "; 
    public $sql_down = "ALTER TABLE `users` DROP COLUMN `oauth_access_token` "; 

    public function up() {
        $this->execute($this->sql_up);
    }

    public function down() {
        $this->execute( $this->sql_down);
    }
}
