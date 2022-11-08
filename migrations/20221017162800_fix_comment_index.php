<?php

class FixCommentIndex extends AbstractMatecatMigration {

    public $sql_up = [ '
        ALTER TABLE comments 
drop index id_segment,
add index id_segment ( `id_segment` );
    ' ];

    public $sql_down = [ '
        ALTER TABLE comments 
drop index id_segment,
add index id_segment ( `id_job` );
    ' ];
}
