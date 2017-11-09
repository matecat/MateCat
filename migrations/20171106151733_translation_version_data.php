<?php

class TranslationVersionData extends AbstractMatecatMigration {
    public $sql_up = <<<EOF
    CREATE TABLE `translation_version_data` (
        id bigint(20) not null AUTO_INCREMENT,
        id_job bigint(20) not null,
        id_segment bigint(20) not null,
        version_number int(11) not null,
        raw_diff TEXT,
        PRIMARY KEY (`id`),
        KEY `id_job` (`id_job`) USING BTREE,
        UNIQUE KEY `jid_seg_version` (`id_job`, `id_segment`, `version_number` ) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
        )
EOF;

    public $sql_down = "DROP TABLE `translation_version_data`" ;

}
