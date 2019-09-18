<?php

class RenameColumnScoreToPenaltyPoints extends AbstractMatecatMigration {

    public $sql_up = 'ALTER TABLE `qa_chunk_reviews` CHANGE `score` `penalty_points` bigint(20);';

    public $sql_down = 'ALTER TABLE `qa_chunk_reviews` CHANGE `penalty_points` `score` bigint(20);';

}
