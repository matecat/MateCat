<?php

use Phinx\Migration\AbstractMigration;

class AddUidToRemoteFiles extends AbstractMatecatMigration
{
    public $sql_up = <<<EOF
    ALTER TABLE remote_files ADD column uid bigint;
    
    ALTER TABLE remote_files ADD INDEX `uid` ( uid ) USING BTREE ;
    
    -- This update populates the newly created uid field. 
    
    UPDATE remote_files 
        JOIN files_job on files_job.id_file = remote_files.id_file 
        JOIN jobs on jobs.id = files_job.id_job 
        JOIN projects on projects.id = jobs.id_project 
        JOIN users on users.email = projects.id_customer 
    SET remote_files.uid = users.uid ; 
    

EOF;

    public $sql_down = <<<EOF
        ALTER TABLE remote_files DROP column uid ; 
EOF;

}
