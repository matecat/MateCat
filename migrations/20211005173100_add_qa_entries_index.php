<?php

class AddQaEntriesIndex extends AbstractMatecatMigration {

    public $sql_up = [
        'alter table qa_entries add index id_segment_idx( id_segment );'
    ];

    public $sql_down = [ 'alter table qa_entries DROP index id_segment_idx;' ];

}