<?php

use Phinx\Migration\AbstractMigration;

class ReplaceEventCreateTable extends AbstractMigration
{
    public $sql_up = <<<EOF
CREATE TABLE `replace_events` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `bulk_version` bigint(20) NOT NULL,
    `id_job` bigint(20) NOT NULL,
    `job_password` varchar(45) NOT NULL,
    `id_segment` int(11) NOT NULL,
    `segment_version` int(11),
    `translation_before_replacement` text,
    `translation_after_replacement` text,
    `source` text,
    `target` text,
    `replacement` text,
    `created_at` datetime NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
EOF;

    public $sql_down = 'DROP TABLE `replace_events`';
}
