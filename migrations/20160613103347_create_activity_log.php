<?php

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
  `memory_key` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`ID`,`event_date`),
  KEY `ip_idx` (`ip`) USING BTREE,
  KEY `id_job_idx` (`id_job`) USING BTREE,
  KEY `id_project_idx` (`id_project`) USING BTREE,
  KEY `uid_idx` (`uid`) USING BTREE,
  KEY `event_date_idx` (`event_date`) USING BTREE
) 

ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8

PARTITION BY RANGE( YEAR(event_date) ) (
    PARTITION p2016 VALUES LESS THAN (2017),
    PARTITION p2017 VALUES LESS THAN (2018),
    PARTITION p2018 VALUES LESS THAN (2019),
    PARTITION p2019 VALUES LESS THAN (2020),
    PARTITION p9999 VALUES LESS THAN MAXVALUE
)
;
EOF;

    public $sql_down = 'DROP TABLE `activity_log`';

}
