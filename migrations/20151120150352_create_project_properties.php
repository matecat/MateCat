<?php

use Phinx\Migration\AbstractMigration;

class CreateProjectProperties extends AbstractMigration {
  public $sql_up = <<<EOF
CREATE TABLE `project_metadata` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_project` bigint(20) NOT NULL,
  `key` varchar(255) NOT NULL,
  `value` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_project` (`id_project`) USING BTREE,
  UNIQUE KEY `id_project_and_key` (`id_project`, `key`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
EOF;

  public $sql_down = 'DROP TABLE `project_metadata`';

  public function up() {
    $this->execute($this->sql_up);
  }

  public function down() {
    $this->execute($this->sql_down);
  }
}
