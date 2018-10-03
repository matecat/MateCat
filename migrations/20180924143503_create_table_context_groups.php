<?php

class CreateTableContextGroups extends AbstractMatecatMigration {

    public $sql_up = <<<EOF
    CREATE TABLE `context_groups` (
        `id`  int NOT NULL AUTO_INCREMENT ,
        `id_project`  int NOT NULL ,
        `id_segment`  bigint UNSIGNED NULL ,
        `id_file`  int UNSIGNED NULL ,
        `context_json`  varchar(16320) NOT NULL ,
        PRIMARY KEY (`id`, `id_project`),
        INDEX `id_segment_idx` (`id_segment`) USING BTREE ,
        INDEX `id_file_idx` (`id_file`) USING BTREE ,
        INDEX `id_project_idx` (`id_project`) USING BTREE 
    )
;
EOF;

    public $sql_down = "DROP TABLE context_groups";

}