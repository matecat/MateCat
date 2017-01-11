<?php


class DropSegmentsIndexes extends AbstractMatecatMigration
{

    public $sql_up = <<<EOF
        ALTER TABLE `segments` 
        DROP INDEX `id_file_part_idx`,
        DROP INDEX `mrk_id`,
        ALGORITHM=INPLACE, LOCK=NONE;
EOF;

    public  $sql_down = <<<EOF
        ALTER TABLE `segments` 
        ADD INDEX `id_file_part_idx` (`id_file_part`),
        ADD INDEX `mrk_id` (`xliff_mrk_id`) USING BTREE;
EOF;

}
