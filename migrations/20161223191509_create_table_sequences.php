<?php

class CreateTableSequences extends AbstractMatecatMigration
{

    public $sql_up = array(
            " CREATE TABLE `sequences` ( `id_segment` BIGINT UNSIGNED NOT NULL ) ENGINE = InnoDB ",
            " LOCK TABLES `sequences` WRITE, `segments` READ ",
            " INSERT INTO `sequences` ( id_segment ) VALUES ( ( SELECT MAX( id ) + 1 FROM segments ) ) ",
            " UNLOCK TABLES ",
    );

    public  $sql_down = <<<EOF
    DROP TABLE `sequences` ;
EOF;


}
