<?php

class CreateTablesForTeams extends AbstractMatecatMigration
{
    public $sql_up = array(
        "CREATE TABLE `teams` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(255) NOT NULL,
      `created_by` bigint(20) NOT NULL,
      `created_at` datetime NOT NULL,
      PRIMARY KEY (`id`)
      )
      ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8
      "
        ,
        "CREATE TABLE `teams_users` (
          `id` INT(11) NOT NULL AUTO_INCREMENT,
          `id_team` INT(11) NOT NULL,
          `uid` BIGINT( 20 ) NOT NULL,
          `is_admin` tinyint(1) NOT NULL DEFAULT 0,
          PRIMARY KEY (`id`),
          UNIQUE KEY `id_team_uid` (`id_team`, `uid`) USING BTREE
      ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8
      "
      ,
      " ALTER TABLE `projects` ADD column `id_team` INT(11) DEFAULT NULL AFTER `id_customer` ; ",

      " ALTER TABLE `owner_features` ADD column `id_team` INT(11) DEFAULT NULL AFTER `uid` ; ",

      " ALTER TABLE `owner_features` MODIFY COLUMN `uid` bigint(20) DEFAULT NULL ",

      " ALTER TABLE `owner_features` ADD UNIQUE KEY `id_team_feature` (`id_team`,`feature_code`) USING BTREE "

    );

    public $sql_down = array(
        " DROP TABLE `teams` ; ",

        " DROP TABLE `teams_users` ; ",

        " ALTER TABLE `projects` DROP COLUMN `id_team` ; ",

        " ALTER TABLE `owner_features` DROP COLUMN `id_team` ; ",

        " ALTER TABLE `owner_features` DROP INDEX `id_team_feature` ; "
    );

}
