<?php


class CreateTableOrganizationsPendingUsers extends AbstractMatecatMigration
{

    public $sql_up = "
    CREATE TABLE `organizations_pending_users` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `invited_by_uid` int(10) unsigned NOT NULL,
      `email` varchar(255) NOT NULL,
      `expire_date` datetime DEFAULT NULL,
      `token` varchar(255) NOT NULL,
      `request_info` varchar(2048) NOT NULL DEFAULT '{}',
      PRIMARY KEY (`id`),
      UNIQUE KEY `token_idx` (`token`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ";

    public $sql_down = "DROP TABLE organizations_pending_users";

}
