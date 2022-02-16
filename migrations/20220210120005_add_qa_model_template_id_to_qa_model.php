<?php

class AddQaModelTemplateIdToQaModel extends AbstractMatecatMigration {

    public $sql_up = [ '
        ALTER TABLE `qa_models` 
        ADD COLUMN `qa_model_template_id` INT(11) NULL DEFAULT NULL AFTER `hash`;
    ' ];

    public $sql_down = [ 'ALTER TABLE `qa_models` DROP COLUMN `qa_model_template_id`;' ];
}
