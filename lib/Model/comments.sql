
-- To be used in conjunction with matecat.sql

DROP TABLE IF EXISTS `comments`;

CREATE TABLE `comments` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `id_job` bigint(20) NOT NULL,
    `id_segment` bigint(20) NOT NULL,
    `create_date` datetime NOT NULL,
    `email` varchar(50) DEFAULT NULL,
    `full_name` varchar(100) NOT NULL,
    `uid` bigint(20) DEFAULT NULL,
    `resolve_date` datetime DEFAULT NULL,
    `source_page` tinyint(4) DEFAULT NULL,
    `is_owner` tinyint(4) NOT NULL,
    `message_type` tinyint(4) DEFAULT NULL,
    `message` text,
    PRIMARY KEY (`id`),
    KEY `id_job` (`id_job`) USING BTREE,
    KEY `id_segment` (`id_job`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

GRANT ALL ON matecat.* TO 'matecat'@'localhost' IDENTIFIED BY 'matecat01';
FLUSH PRIVILEGES;
