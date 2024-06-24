<?php

class DropColumnDqfIdFromQASeverities extends AbstractMatecatMigration {

    public $sql_up = [ '
        ALTER TABLE qa_model_template_severities drop column dqf_id;
    ' ];

    public $sql_down = [ '
        ALTER TABLE qa_model_template_severities add column dqf_id int(11) DEFAULT NULL;
    ' ];
}

