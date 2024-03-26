<?php

class DropDqfProjectsMap extends AbstractMatecatMigration {

    public $sql_up = [ '
        DROP TABLE `dqf_projects_map`;
    ' ];

    public $sql_down = [ '
        CREATE TABLE `dqf_projects_map` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `id_job` bigint(20) NOT NULL,
          `password` varchar(50) NOT NULL,
          `first_segment` int(11) NOT NULL,
          `last_segment` int(11) NOT NULL,
          `dqf_project_id` int(11) NOT NULL,
          `dqf_project_uuid` varchar(255) NOT NULL,
          `dqf_parent_uuid` varchar(255) DEFAULT NULL,
          `archive_date` datetime DEFAULT NULL,
          `create_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `project_type` varchar(50) DEFAULT NULL,
          `uid` bigint(20) DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `id_job` (`id_job`) USING BTREE,
          KEY `first_last_segment` (`first_segment`,`last_segment`) USING BTREE
        ) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8;
    ' ];
}
