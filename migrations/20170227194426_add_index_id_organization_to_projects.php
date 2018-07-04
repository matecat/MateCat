<?php


class AddIndexIdOrganizationToProjects extends AbstractMatecatMigration
{

    public $sql_up = "ALTER TABLE `projects` ADD INDEX `id_organization_idx` ( `id_organization` ) USING BTREE, algorithm=INPLACE, lock=NONE";

    public $sql_down = " ALTER TABLE `projects` DROP INDEX `id_organization_idx` ";

}
