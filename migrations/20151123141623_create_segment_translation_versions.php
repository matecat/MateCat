<?php

class CreateSegmentTranslationVersions extends AbstractMatecatMigration {
  public $sql_up = <<<EOF
CREATE TABLE `segment_translation_versions` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_segment` bigint(20) NOT NULL,
  `id_job` bigint(20) NOT NULL,
  `translation` text,
  `version_number` int(11) NOT NULL,
  `creation_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `propagated_from` bigint(20),
  PRIMARY KEY (`id`),
  KEY `id_segment` (`id_segment`) USING BTREE,
  KEY `id_job` (`id_job`) USING BTREE,
  KEY `creation_date` (`creation_date`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
EOF;

  public $sql_down = 'DROP TABLE `segment_translation_versions`';

}
