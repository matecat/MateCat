<?php

use Phinx\Migration\AbstractMigration;

class PrepareOrganizations extends AbstractMigration {

    public function up() {

        $this->execute( " ALTER TABLE `organizations` ADD COLUMN `type` VARCHAR(45) NOT NULL DEFAULT 'personal' AFTER `created_at` " );
        $this->execute( " ALTER TABLE `organizations_users` ADD INDEX `uid` (`uid` ASC) " );
        $this->execute( "
            CREATE TABLE `workspaces` (
              `id` INT NOT NULL AUTO_INCREMENT,
              `name` VARCHAR(255) NOT NULL,
              `id_organization` INT NOT NULL,
              `options` VARCHAR(10240) NULL DEFAULT '{}',
              PRIMARY KEY (`id`) )
            ENGINE = InnoDB
        " );

    }

    public function down() {

        $this->execute( " ALTER TABLE `organizations` DROP COLUMN type " );
        $this->execute( " ALTER TABLE `organizations_users` DROP INDEX `uid` " );
        $this->execute( " DROP TABLE `workspaces` " );

    }
}


