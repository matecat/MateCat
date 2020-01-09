<?php

class AlterTableQaModels extends AbstractMatecatMigration {

    public $sql_up = <<<EOF
      ALTER TABLE qa_models ADD COLUMN `hash` INTEGER UNSIGNED DEFAULT NULL, ALGORITHM=INPLACE, LOCK=NONE;
EOF;

    public $sql_down = <<<EOF
      ALTER TABLE qa_models DROP COLUMN `hash`;
EOF;

}
