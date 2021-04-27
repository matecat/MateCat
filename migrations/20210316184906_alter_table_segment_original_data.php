<?php

class AlterTableSegmentOriginalData extends AbstractMatecatMigration {

    public $sql_up = [ 'CREATE index id_segment_idx on segment_original_data( id_segment )' ];

    public $sql_down = [ 'DROP INDEX `id_segment_idx` ON segment_original_data' ];

}
