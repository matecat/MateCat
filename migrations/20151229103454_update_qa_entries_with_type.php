<?php

class UpdateQaEntriesWithType extends AbstractMatecatMigration {
    public $sql_up = <<<EOF
ALTER TABLE `qa_models`
    ADD COLUMN `pass_type` varchar(255) ,
    ADD COLUMN `pass_options` text
;
EOF;

    public $sql_down = <<<EOF
ALTER TABLE `qa_models`
    DROP COLUMN `pass_type` ,
    DROP COLUMN `pass_options` ;
EOF;
}
