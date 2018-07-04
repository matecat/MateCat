<?php

class CreateQaChunkReviews extends AbstractMatecatMigration {

    public $sql_up = <<<EOF
CREATE TABLE `qa_chunk_reviews` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `id_project` int(11) NOT NULL,
    `id_job` bigint(20) NOT NULL,
    `password` varchar(45) NOT NULL,
    `review_password` varchar(45) NOT NULL,
    `score` bigint(20) NOT NULL DEFAULT 0,
    `num_errors` int(11) NOT NULL DEFAULT 0,
    `is_pass` tinyint(4) NOT NULL DEFAULT 0,
    `force_pass_at` timestamp NULL DEFAULT NULL ,
    PRIMARY KEY (`id`),
    KEY `id_project` (`id_project`),
    KEY `review_password` (`review_password`),
    KEY `id_job` (`id_job`),
    UNIQUE KEY `id_job_password` (`id_job`, `password`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
EOF;

    public $sql_down = <<<EOF
DROP TABLE `qa_chunk_reviews`;
EOF;

}
