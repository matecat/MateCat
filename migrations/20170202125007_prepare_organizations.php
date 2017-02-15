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
            ADD COLUMN `id_assignee` INT UNSIGNED NULL DEFAULT NULL,
            ADD COLUMN `id_workspace` INT UNSIGNED NULL DEFAULT NULL,
            ADD INDEX `id_assignee_idx` (`id_assignee` ASC),
            ADD INDEX `id_workspace_idx` (`id_workspace` ASC),
            algorithm=INPLACE, lock=NONE
        " );

    }

    public function down() {

        $this->execute( " ALTER TABLE `organizations` DROP COLUMN `type` " );
        $this->execute( " ALTER TABLE `organizations_users` DROP INDEX `uid` " );
        $this->execute( " DROP TABLE `workspaces` " );
        $this->execute( " 
              ALTER TABLE `projects` 
              DROP COLUMN `id_assignee`,
              DROP INDEX `id_assignee_idx`,
              DROP COLUMN `id_workspace`,
              DROP INDEX `id_workspace_idx`
        " );

    }
}


