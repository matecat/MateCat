<?php

use Phinx\Migration\AbstractMigration;

class CreateTablesForQaModel extends AbstractMigration {

  public $sql_up = <<<EOF
CREATE TABLE `qa_entries` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `uid` bigint(20) ,
    `id_segment` bigint(20) NOT NULL,
    `id_job` bigint(20) NOT NULL,
    `id_category` bigint(20) NOT NULL,
    `severity` VARCHAR(255) NOT NULL,
    `translation_version` int(11) NOT NULL,
    `start_position` INT NOT NULL,
    `stop_position` INT NOT NULL,
    `target_text` VARCHAR(255),
    `is_full_segment` tinyint(4) NOT NULL,
    `penalty_points` INT(11) NOT NULL,
    `comment` TEXT,
    `create_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `job_and_segment` (`id_job`, `id_segment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `qa_categories` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `id_model` bigint(20) NOT NULL,
    `label` VARCHAR(255) NOT NULL,
    `id_parent` bigint(20),
    `severities` TEXT  COMMENT 'json field',
    PRIMARY KEY (`id`),
    KEY `id_model` (`id_model`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `qa_models` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `uid` bigint(20) ,
    `create_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    `label` VARCHAR(255),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `projects` ADD COLUMN `id_qa_model` int(11) DEFAULT NULL ;

EOF;

  public $sql_down = <<<EOF

DROP TABLE IF EXISTS `qa_models`;
DROP TABLE IF EXISTS `qa_categories`;
DROP TABLE IF EXISTS `qa_entries`;
ALTER TABLE `projects` DROP COLUMN `id_qa_model` ;

EOF;

    public function up() {
        $this->execute($this->sql_up);
    }

    public function down() {
        $this->execute($this->sql_down);
    }

}
