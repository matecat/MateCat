<?php

class ReplaceEventCurrentVersion extends AbstractMatecatMigration
{
    public $sql_up = "CREATE TABLE `replace_events_current_version` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `id_job` bigint(20) NOT NULL,
    `version` bigint(20) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;";

    public $sql_down = "DROP TABLE `replace_events_current_version`";
}
