<?php

class AddConnectedServiceIdToRemoteFiles extends AbstractMatecatMigration
{

    public $sql_up = <<<EOF
    ALTER TABLE remote_files ADD column connected_service_id bigint;

    ALTER TABLE remote_files ADD INDEX `connected_service_id` ( connected_service_id ) USING BTREE ;

    UPDATE remote_files
        JOIN files_job on files_job.id_file = remote_files.id_file
        JOIN jobs on jobs.id = files_job.id_job
        JOIN projects on projects.id = jobs.id_project
        JOIN users on users.email = projects.id_customer
        JOIN connected_services ON connected_services.uid = users.uid

    SET remote_files.connected_service_id = connected_services.id  ;

EOF;

    public $sql_down = <<<EOF
        ALTER TABLE remote_files DROP column connected_service_id ;
EOF;



}
