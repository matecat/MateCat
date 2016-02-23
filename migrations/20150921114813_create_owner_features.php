<?php

class CreateOwnerFeatures extends AbstractMatecatMigration
{

    public $sql_up = <<<EOF
CREATE TABLE `owner_features` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `uid` bigint(20) NOT NULL,
    `feature_code` varchar(45) NOT NULL,
    `options` text,
    `create_date` datetime NOT NULL,
    `last_update` datetime NOT NULL,
    `enabled` tinyint(1) NOT NULL DEFAULT '1',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uid_feature` (`uid`, `feature_code`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
EOF;

    public $sql_down = 'DROP TABLE `owner_features`';

}
