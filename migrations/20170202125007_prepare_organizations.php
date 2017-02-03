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
        $this->execute( "
            ALTER TABLE `projects`
            ADD COLUMN `assegnee_uid` INT UNSIGNED NULL DEFAULT NULL,
            ADD COLUMN `workspace_id` INT UNSIGNED NULL DEFAULT NULL,
            ADD INDEX `assegnee_uid_idx` (`assegnee_uid` ASC), 
            ADD INDEX `workspace_id_idx` (`assegnee_uid` ASC), 
            algorithm=INPLACE, lock=NONE
        " );

    }

    public function down() {

        $this->execute( " ALTER TABLE `organizations` DROP COLUMN `type` " );
        $this->execute( " ALTER TABLE `organizations_users` DROP INDEX `uid` " );
        $this->execute( " DROP TABLE `workspaces` " );
        $this->execute( " 
              ALTER TABLE `projects` 
              DROP COLUMN `assegnee_uid`, 
              DROP INDEX `assegnee_uid_idx`, 
              DROP COLUMN `workspace_id`, 
              DROP INDEX `workspace_id_idx` 
        " );

    }
}


