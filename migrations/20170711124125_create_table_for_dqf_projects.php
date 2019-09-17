<?php

class CreateTableForDqfProjects extends AbstractMatecatMigration
{
    public $sql_up = "CREATE TABLE `dqf_child_projects_map` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_job` BIGINT(20)  NOT NULL,
  `password` VARCHAR(50) NOT NULL,
  `first_segment` INT(11) NOT NULL,
  `last_segment` INT(11) NOT NULL,
  `dqf_project_id` INT(11) NOT NULL,
  `dqf_project_uuid` VARCHAR(255) NOT NULL,
  `dqf_parent_uuid` VARCHAR (255) NULL,
  `archive_date` DATETIME NULL ,
  `create_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ,
  PRIMARY KEY (`id`),
  KEY `id_job` ( `id_job` ) USING BTREE,
  KEY `first_last_segment` ( `first_segment`, `last_segment` )  USING BTREE
  )
  ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;";

    public $sql_down = "DROP TABLE `dqf_child_projects_map`";
}
