<?php

class PopulateRemoteServices extends AbstractMatecatMigration
{
    public $sql_up = <<<EOF
        INSERT INTO connected_services ( is_default, email, name, uid, oauth_access_token, service, created_at )
            SELECT 1, users.email, concat(users.first_name, ' ', users.last_name), users.uid, users.oauth_access_token, 'gdrive', CURRENT_DATE
            FROM users 
                JOIN projects on users.email = projects.id_customer 
                JOIN jobs on projects.id = jobs.id_project 
                JOIN remote_files on remote_files.id_job = jobs.id
                
                GROUP BY users.email, users.uid, users.oauth_access_token
            ; 
EOF;

    public $sql_down = <<<EOF
        DELETE FROM connected_services ; 
EOF;

}
