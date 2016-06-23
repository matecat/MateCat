<?php

use Phinx\Migration\AbstractMigration;

class CreateActivityLog extends AbstractMatecatMigration
{

    public $sql_up = <<<EOF
CREATE TABLE `activity_log` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `id_project` int(10) unsigned DEFAULT NULL,
  `id_job` int(10) unsigned DEFAULT NULL,
  `action` int(10) unsigned NOT NULL,
  `ip` varchar(45) NOT NULL,
  `uid` int(10) unsigned DEFAULT NULL,
  `event_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `ip_idx` (`ip`) USING BTREE,
  KEY `id_job_idx` (`id_job`) USING BTREE,
  KEY `id_project_idx` (`id_project`) USING BTREE,
  KEY `uid_idx` (`uid`) USING BTREE,
  KEY `event_date_idx` (`event_date`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8
EOF;

    public $sql_down = 'DROP TABLE `activity_log`';

}
