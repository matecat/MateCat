<?php

use Phinx\Migration\AbstractMigration;

class OrganizationTimestamp extends AbstractMigration
{
    public function up() {

        $this->execute( "ALTER TABLE `matecat`.`organizations` CHANGE COLUMN `created_at` `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP " );

    }

    public function down() {

        $this->execute( " ALTER TABLE `matecat`.`organizations` CHANGE COLUMN `created_at` `created_at` DATETIME NOT NULL  " );

    }
}
