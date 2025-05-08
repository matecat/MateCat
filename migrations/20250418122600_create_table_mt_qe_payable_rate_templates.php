<?php

class CreateTableMtQePayableRateTemplates extends AbstractMatecatMigration {

    public $sql_up = [
            '
                CREATE TABLE `mt_qe_payable_rate_templates` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(255) CHARACTER SET latin1 DEFAULT NULL,
                `uid` bigint(20) NOT NULL,
                `version` int(11) NOT NULL DEFAULT 1,
                `breakdowns` text NOT NULL,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `modified_at` timestamp NULL DEFAULT NULL,
                `deleted_at` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uid_name_idx` (`uid`,`name`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8
            ',
    ];

    public $sql_down = [
            'DROP TABLE `mt_qe_payable_rate_templates`;',
    ];

}


