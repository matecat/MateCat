<?php


class RemoveWorkspaces extends AbstractMatecatMigration
{

    public $sql_up = [
            " DROP TABLE `workspaces`; ",
            " ALTER TABLE `projects` 
                DROP COLUMN `id_workspace`, DROP INDEX `id_workspace_idx` ,
                CHANGE COLUMN `id_organization` `id_team` INT(11) NULL DEFAULT NULL ,
                DROP INDEX `id_organization_idx` ,
                ADD INDEX `id_team_idx` USING BTREE (`id_team` ASC);",
            "ALTER TABLE `organizations` RENAME TO `teams` ;",
            "ALTER TABLE `organizations_users` 
                CHANGE COLUMN `id_organization` `id_team` INT(11) NULL DEFAULT NULL ,
                DROP INDEX `id_organization_uid` ,
                ADD UNIQUE INDEX `id_team_uid` USING BTREE (`id_team` ASC, `uid` ASC), RENAME TO `teams_users` ;",
            "ALTER TABLE `owner_features` 
                CHANGE COLUMN `id_organization` `id_team` INT(11) NULL DEFAULT NULL ,
                DROP INDEX `id_organization_feature` ,
                ADD UNIQUE INDEX `id_team_feature` USING BTREE (`id_team` ASC, `feature_code` ASC);
            "

    ];

    public $sql_down = [

            " ALTER TABLE `projects`
                CHANGE COLUMN `id_team` `id_organization` INT(11) NULL DEFAULT NULL ,
                ADD COLUMN `id_workspace` INT UNSIGNED NULL DEFAULT NULL,
                ADD INDEX `id_workspace_idx` (`id_workspace` ASC),
                DROP INDEX `id_team_idx`,
                ADD INDEX `id_organization_idx` USING BTREE (`id_organization` ASC),
                algorithm=INPLACE, lock=NONE; ",

            " CREATE TABLE `workspaces` (
              `id` INT NOT NULL AUTO_INCREMENT,
              `name` VARCHAR(255) NOT NULL,
              `id_organization` INT NOT NULL,
              `options` VARCHAR(10240) NULL DEFAULT '{}',
              PRIMARY KEY (`id`) )
            ENGINE = InnoDB;
            ",
            "ALTER TABLE `teams` RENAME TO `organizations` ;",
            "ALTER TABLE `teams_users` 
                CHANGE COLUMN `id_team` `id_organization` INT(11) NULL DEFAULT NULL ,
                DROP INDEX `id_team_uid` ,
                ADD UNIQUE INDEX `id_organization_uid` USING BTREE (`id_organization` ASC, `uid` ASC), RENAME TO `organizations_users` ;",
            "ALTER TABLE `owner_features`
                CHANGE COLUMN `id_team` `id_organization` INT(11) NULL DEFAULT NULL ,
                DROP INDEX `id_team_feature` ,
                ADD UNIQUE INDEX `id_organization_feature` USING BTREE (`id_organization` ASC, `feature_code` ASC);
            "

    ];

}
