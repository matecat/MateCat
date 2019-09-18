<?php

class AddEquivalentWcToChunkReview extends AbstractMatecatMigration
{

    public $sql_up   = [ "ALTER TABLE qa_chunk_reviews ADD COLUMN advancement_wc FLOAT(10,2) NOT NULL DEFAULT '0.00'", ];
    public $sql_down = [ "ALTER TABLE qa_chunk_reviews DROP COLUMN advancement_wc " ];
}
