<?php

class AlterTableFiles extends AbstractMatecatMigration
{

    public $sql_up = <<<EOF
ALTER TABLE files
DROP COLUMN xliff_file,
DROP COLUMN original_file;
EOF;

    public  $sql_down = <<<EOF
    ALTER TABLE files
    ADD COLUMN xliff_file longblob AFTER mime_type,
    ADD COLUMN original_file longblob;
EOF;

}
