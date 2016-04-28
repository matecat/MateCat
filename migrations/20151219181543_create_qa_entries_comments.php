<?php

class CreateQaEntriesComments extends AbstractMatecatMigration {

    public $sql_up = <<<EOF
CREATE TABLE `qa_entry_comments` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `uid` bigint(20) ,
    `id_qa_entry` bigint(20) NOT NULL,
    `create_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `comment` TEXT,
    PRIMARY KEY (`id`),
    KEY `id_qa_entry` (`id_qa_entry`),
    KEY `create_date` (`create_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
EOF;

    public $sql_down = <<<EOF
DROP TABLE IF EXISTS `qa_entry_comments`;
EOF;

}
