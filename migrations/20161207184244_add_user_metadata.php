<?php

class AddUserMetadata extends AbstractMatecatMigration
{

    public $sql_up = <<<EOF
CREATE TABLE `user_metadata` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` bigint(20) NOT NULL,
  `key` varchar(255) NOT NULL,
  `value` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`) USING BTREE,
  UNIQUE KEY `uid_and_key` (`uid`, `key`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

EOF;

    public  $sql_down = <<<EOF
    DROP TABLE `user_metadata` ;
EOF;

}
