<?php

class CreateProjectSequence extends AbstractMatecatMigration
{

    public $sql_up = array(
            " ALTER TABLE `sequences` ADD COLUMN `id_project` BIGINT(20) UNSIGNED NOT NULL AFTER `id_segment`; ",
            " LOCK TABLES `sequences` WRITE, `projects` READ ",
            " UPDATE `sequences` SET id_project = ( SELECT MAX( id ) + 1 FROM projects ) ",
            " UNLOCK TABLES ",
    );

    public  $sql_down = <<<EOF
    ALTER TABLE `sequences` DROP COLUMN `id_project`;
EOF;


}
