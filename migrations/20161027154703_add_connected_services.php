<?php

class AddConnectedServices extends AbstractMatecatMigration
{
    public $sql_up = <<<EOF

    CREATE TABLE `connected_services` (

        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `uid` bigint(20) NOT NULL,
        `service` varchar(30) NOT NULL,
        
        `remote_id` varchar(255), 
        `name` varchar(255) NOT NULL, 
        `email` varchar(255) NOT NULL,
        
        `oauth_access_token` text NOT NULL,

        `created_at` timestamp NOT NULL,
        `updated_at` timestamp,
        
        `expired_at` timestamp,
        `disabled_at` timestamp,
        
        `is_default` tinyint(4) NOT NULL DEFAULT 0,

        PRIMARY KEY ( `id` ),
        UNIQUE KEY `uid_email_service` ( `uid`, `email`, `service` ) USING BTREE

        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

EOF;

    public $sql_down = <<<EOF
    DROP table `connected_services` ;
EOF;


}
