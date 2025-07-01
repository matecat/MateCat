<?php

class AddMtQualityValueInEditorToProjectTemplates extends AbstractMatecatMigration {

    public $sql_up = [
        'ALTER TABLE `project_templates` add column `mt_quality_value_in_editor` int(11) DEFAULT NULL;',
    ];

    
    public $sql_down = [
        'ALTER TABLE `project_templates` DROP COLUMN `mt_quality_value_in_editor`;',
    ];

}