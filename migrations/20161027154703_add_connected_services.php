<?php

use Phinx\Migration\AbstractMigration;

class AddConnectedServices extends AbstractMatecatMigration
{
    public $sql_up = <<<EOF

    CREATE TABLE `connected_services` (

        `id` int(11) NOT NULL AUTO_INCREMENT,
        `uid` int(11) NOT NULL,
        `service` varchar(30) NOT NULL,
        `oauth_access_token` varchar(255) NOT NULL,
        `created_at` timestamp NOT NULL,
        `expires_at` timestamp,
        `last_usage_at` timestamp,
        `refreshed_at` timestamp, 
        `refresh_count` int NOT NULL DEFAULT 0, 
        `expired_at` timestamp,

        PRIMARY KEY ( `id` ),
        UNIQUE KEY `uid_service` ( `uid`, `service` ) USING BTREE

        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

EOF;

    public $sql_down = <<<EOF
    DROP table `connected_services` ;
EOF;


}
