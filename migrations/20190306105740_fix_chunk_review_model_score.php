<?php


class FixChunkReviewModelScore extends AbstractMatecatMigration
{
    public $sql_up = <<<EOF
      ALTER TABLE `qa_chunk_reviews` MODIFY penalty_points DOUBLE(20, 2) ; 
      ALTER TABLE `qa_entries` MODIFY penalty_points DOUBLE(20, 2) ; 
EOF;

    public $sql_down = <<<EOF
      ALTER TABLE `qa_chunk_reviews` MODIFY penalty_points BIGINT(20) ; 
      ALTER TABLE `qa_chunk_reviews` MODIFY penalty_points INT(11) ; 
EOF;

}

