<?php

use Phinx\Migration\AbstractMigration;

class PopulateRemoteServices extends AbstractMatecatMigration
{
    public $sql_up = <<<EOF
        INSERT INTO connected_services ( uid, oauth_access_token, service, created_at ) 
            SELECT users.uid, users.oauth_access_token, 'gdrive', CURRENT_DATE 
            FROM users where uid IN ( 
                SELECT uid 
                 FROM remote_files 
                 GROUP BY uid 
                 ORDER BY uid 
            ); 
EOF;

    public $sql_down = <<<EOF
        DELETE FROM connected_services ; 
EOF;

}
