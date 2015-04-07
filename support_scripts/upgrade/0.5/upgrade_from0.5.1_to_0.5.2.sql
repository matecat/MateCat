USE matecat;
CREATE TABLE `segment_translations_splits` (
    `id_segment`  bigint(20) NOT NULL ,
    `id_job`  bigint(20) NOT NULL ,
    `source_chunk_lengths`  varchar(1024) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '[]' ,
    `target_chunk_lengths`  varchar(1024) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '{\"len\":[0],\"statuses\":[\"DRAFT\"]}' ,
    PRIMARY KEY (`id_segment`, `id_job`),
    INDEX `id_job` (`id_job`) USING BTREE ,
    INDEX `id_segment` (`id_segment`) USING BTREE
)
ENGINE=InnoDB
DEFAULT CHARACTER SET=utf8;