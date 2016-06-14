<?php

use Phinx\Migration\AbstractMigration;

class CreateActivityLog extends AbstractMatecatMigration
{

    public $sql_up = <<<EOF
CREATE TABLE `activity_log` (
  `ID` INT NOT NULL AUTO_INCREMENT,
  `id_project` INT UNSIGNED NOT NULL,
  `id_job` INT UNSIGNED NOT NULL,
  `action` INT UNSIGNED NOT NULL,
  `ip` VARCHAR(45) NOT NULL,
  `uid` INT UNSIGNED NOT NULL,
  `event_date` DATETIME NOT NULL DEFAULT NOW(),
  PRIMARY KEY (`ID`),
  INDEX `ip_idx` USING BTREE (`ip` ASC),
  INDEX `id_job_idx` USING BTREE (`id_job` DESC),
  INDEX `id_project_idx` USING BTREE (`id_project` DESC),
  INDEX `uid_idx` USING BTREE (`uid` ASC),
  INDEX `event_date_idx` USING BTREE (`event_date` ASC));
EOF;

    public $sql_down = 'DROP TABLE `activity_log`';

}
