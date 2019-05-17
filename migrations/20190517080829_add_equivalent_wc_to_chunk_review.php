<?php

use Phinx\Migration\AbstractMigration;

class AddEquivalentWcToChunkReview extends AbstractMatecatMigration
{

    public $sql_up   = [ "ALTER TABLE qa_chunk_reviews ADD COLUMN eq_reviewed_words_count FLOAT(10,2) NOT NULL DEFAULT '0.00'", ];
    public $sql_down = [ "ALTER TABLE qa_chunk_reviews DROP COLUMN eq_reviewed_words_count " ];
}
