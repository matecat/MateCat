<?php

class FixWrongProjectIndexes extends AbstractMatecatMigration {

    public $sql_up = array(
            " ALTER TABLE `projects` 
                DROP INDEX workspace_id_idx,
                ADD INDEX workspace_id_idx ( workspace_id ASC );
            "
    );

    public  $sql_down = <<<EOF
            ALTER TABLE `projects` 
                DROP INDEX workspace_id_idx,
                ADD INDEX workspace_id_idx ( assegnee_uid ASC );
EOF;

}


