<?php

use Phinx\Migration\AbstractMigration;

class CreateSegmentTranslationVersions extends AbstractMigration {
  public $sql_up = <<<EOF
CREATE TABLE `segment_translation_versions` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_segment` bigint(20) NOT NULL,
  `id_job` bigint(20) NOT NULL,
  `translation` text,
  `source_page` tinyint(4) DEFAULT NULL,
  `creation_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `uid` bigint(20) DEFAULT NULL,
  `propagated_from` bigint(20),
  PRIMARY KEY (`id`),
  KEY `id_segment` (`id_segment`) USING BTREE,
  KEY `id_job` (`id_job`) USING BTREE,
  KEY `creation_date` (`creation_date`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
EOF;

  public $sql_down = 'DROP TABLE `segment_translation_versions`';

  public function up() {
    $this->execute($this->sql_up);
  }

  public function down() {
    $this->execute($this->sql_down);
  }
}
