<?php


class AlterIsPassQaChunkReview extends AbstractMatecatMigration {

    public $sql_up = <<<EOF
    ALTER TABLE `qa_chunk_reviews` 
          MODIFY COLUMN `is_pass` tinyint(4) NULL DEFAULT NULL, 
          ALGORITHM=INPLACE, LOCK=NONE;
EOF;


    public $sql_down = "ALTER TABLE `qa_chunk_reviews` MODIFY COLUMN `is_pass` tinyint(4) NOT NULL DEFAULT '0'";

}