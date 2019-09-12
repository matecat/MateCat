<?php

class PrepareOrganizations extends AbstractMatecatMigration  {

    public $sql_up = array(
        " ALTER TABLE `organizations` ADD COLUMN `type` VARCHAR(45) NOT NULL DEFAULT 'personal' AFTER `created_at` " ,
        " ALTER TABLE `organizations_users` ADD INDEX `uid` (`uid` ASC) " ,
        "
            CREATE TABLE `workspaces` (
              `id` INT NOT NULL AUTO_INCREMENT,
              `name` VARCHAR(255) NOT NULL,
              `id_organization` INT NOT NULL,
              `options` VARCHAR(10240) NULL DEFAULT '{}',
              PRIMARY KEY (`id`) )
            ENGINE = InnoDB
        ",
        " ALTER TABLE `projects`
            ADD COLUMN `id_assignee` INT UNSIGNED NULL DEFAULT NULL,
            ADD COLUMN `id_workspace` INT UNSIGNED NULL DEFAULT NULL,
            ADD INDEX `id_assignee_idx` (`id_assignee` ASC),
            ADD INDEX `id_workspace_idx` (`id_workspace` ASC),
            algorithm=INPLACE, lock=NONE
        "
    );

    public $sql_down = array(
        " ALTER TABLE `organizations` DROP COLUMN `type` ",
        " ALTER TABLE `organizations_users` DROP INDEX `uid` ",
        " DROP TABLE `workspaces` ",
        " ALTER TABLE `projects`
              DROP COLUMN `id_assignee`,
              DROP INDEX `id_assignee_idx`,
              DROP COLUMN `id_workspace`,
              DROP INDEX `id_workspace_idx`
        ");

}


