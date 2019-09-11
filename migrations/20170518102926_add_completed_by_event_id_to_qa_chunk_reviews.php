<?php

class AddCompletedByEventIdToQaChunkReviews extends AbstractMatecatMigration
{
    public $sql_up = "
        ALTER TABLE `qa_chunk_reviews` 
          ADD COLUMN `undo_data` TEXT DEFAULT NULL, 
          ALGORITHM=INPLACE, LOCK=NONE;" ;

    public $sql_down = "ALTER TABLE `qa_chunk_reviews` DROP COLUMN `undo_data` ; ";

}
