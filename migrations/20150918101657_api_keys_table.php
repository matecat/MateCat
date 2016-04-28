<?php

class ApiKeysTable extends AbstractMatecatMigration {
    public $sql_up = <<<EOF
CREATE TABLE `api_keys` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `uid` bigint(20) NOT NULL,
    `api_key` varchar(50) NOT NULL,
    `api_secret` varchar(45) NOT NULL,
    `create_date` datetime NOT NULL,
    `last_update` datetime NOT NULL,
    `enabled` tinyint(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `api_key` (`api_key`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
EOF;

    public $sql_down = 'DROP TABLE `api_keys`';

}
