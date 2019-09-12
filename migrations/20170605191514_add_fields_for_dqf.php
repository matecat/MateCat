<?php

class AddFieldsForDqf extends AbstractMatecatMigration
{

    public $sql_up = [
            "ALTER TABLE qa_categories ADD COLUMN options TEXT ",

    ];

    public $sql_down = [
            "ALTER TABLE qa_categories ADD DROP COLUMN options ",
    ];

}
