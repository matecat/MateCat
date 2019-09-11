<?php

class RenameDqfChildProjectsMap extends AbstractMatecatMigration {

    public $sql_up = "ALTER TABLE dqf_child_projects_map RENAME TO `dqf_projects_map` " ;
    public $sql_down = "ALTER TABLE dqf_projects_map RENAME TO `dqf_child_projects_map` " ;

}
