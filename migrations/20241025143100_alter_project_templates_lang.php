<?php

class AlterTemplateProjectTable extends AbstractMatecatMigration {

    public $sql_up = [
        'ALTER TABLE `project_templates` MODIFY `target_language` VARCHAR(2048), ALGORITHM=COPY, LOCK=SHARED',
    ];

    
    public $sql_down = [
            'ALTER TABLE `project_templates` MODIFY `target_language` VARCHAR(255), ALGORITHM=COPY, LOCK=SHARED',
    ];

}