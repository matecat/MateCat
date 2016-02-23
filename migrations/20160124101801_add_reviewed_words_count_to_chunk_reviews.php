<?php

class AddReviewedWordsCountToChunkReviews extends AbstractMatecatMigration
{
    public $sql_up = <<<EOF
ALTER TABLE `qa_chunk_reviews` ADD COLUMN `reviewed_words_count` integer NOT NULL DEFAULT 0 ;
EOF;

    public $sql_down = <<<EOF
ALTER TABLE `qa_chunk_reviews` DROP COLUMN `reviewed_words_count` ;
EOF;
    
}
