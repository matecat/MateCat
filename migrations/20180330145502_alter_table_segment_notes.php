<?php

class AlterTableSegmentNotes extends AbstractMatecatMigration {

    public $sql_up = <<<EOF
    ALTER TABLE `segment_notes` 
          MODIFY COLUMN `note` TEXT DEFAULT NULL, 
          ALGORITHM=INPLACE, LOCK=NONE;
EOF;


    public $sql_down = "ALTER TABLE `segment_notes` MODIFY COLUMN `note` TEXT NOT NULL";

}
