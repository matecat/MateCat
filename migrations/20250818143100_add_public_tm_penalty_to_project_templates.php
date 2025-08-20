<?php

use migrations\AbstractMatecatMigration;

class AddPublicTmPenaltyToProjectTemplates extends AbstractMatecatMigration {

    public $sql_up = [
        'ALTER TABLE `project_templates` add column `public_tm_penalty` int(11) DEFAULT 0;',
    ];

    
    public $sql_down = [
        'ALTER TABLE `project_templates` DROP COLUMN `public_tm_penalty`;',
    ];

}