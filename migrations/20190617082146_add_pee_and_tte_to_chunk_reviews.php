<?php

class AddPeeAndTteToChunkReviews extends AbstractMatecatMigration
{

    public $sql_up   = [
            "ALTER TABLE qa_chunk_reviews ADD COLUMN total_tte BIGINT(20) NOT NULL DEFAULT '0' ",
            "ALTER TABLE qa_chunk_reviews ADD COLUMN avg_pee FLOAT(10,2) NOT NULL DEFAULT '0.00' ",
    ];

    public $sql_down = [
            "ALTER TABLE qa_chunk_reviews DROP COLUMN total_tte ",
            "ALTER TABLE qa_chunk_reviews DROP COLUMN avg_pee ",
    ];

}
