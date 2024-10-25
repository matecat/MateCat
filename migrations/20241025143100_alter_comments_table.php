<?php

class AlterTemplateProjectTable extends AbstractMatecatMigration {

    public $sql_up = [
        'ALTER TABLE `comments` CHANGE COLUMN `is_owner` `is_anonymous` tinyint(4) NOT NULL DEFAULT 0, ALGORITHM=INPLACE, LOCK=NONE',
    ];

    
    public $sql_down = [
            'ALTER TABLE `comments` CHANGE COLUMN `is_anonymous` `is_owner` tinyint(4) NOT NULL DEFAULT 0, ALGORITHM=INPLACE, LOCK=NONE',
    ];

}