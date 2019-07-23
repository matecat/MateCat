<?php

use Phinx\Migration\AbstractMigration;

class ReplaceEventCurrentVersion extends AbstractMigration
{
    public $sql_up = <<<EOF
CREATE TABLE `replace_events_current_version` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `id_job` bigint(20) NOT NULL,
    `version` bigint(20) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
EOF;

    public $sql_down = 'DROP TABLE `replace_events_current_version`';
}
