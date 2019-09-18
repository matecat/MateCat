<?php

class AddTteToSegmentTranslationEvents extends AbstractMatecatMigration
{
    public $sql_up = [
            "ALTER TABLE segment_translation_events ADD COLUMN time_to_edit int(11) DEFAULT 0" ,
    ] ;

    public $sql_down = [
            "ALTER TABLE segment_translation_events DROP COLUMN `time_to_edit`" ,
    ] ;
}
