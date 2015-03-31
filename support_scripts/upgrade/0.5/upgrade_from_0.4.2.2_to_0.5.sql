USE matecat;

ALTER TABLE `jobs`
ADD COLUMN `revision_stats_typing`  int(11) NOT NULL DEFAULT 0 AFTER `payable_rates`,
ADD COLUMN `revision_stats_translations`  int(11) NOT NULL DEFAULT 0 AFTER `revision_stats_typing`,
ADD COLUMN `revision_stats_terminology`  int(11) NOT NULL DEFAULT 0 AFTER `revision_stats_translations`,
ADD COLUMN `revision_stats_language_quality`  int(11) NOT NULL DEFAULT 0 AFTER `revision_stats_terminology`,
ADD COLUMN `revision_stats_style`  int(11) NOT NULL DEFAULT 0 AFTER `revision_stats_language_quality`;

ALTER TABLE `jobs`
CHANGE COLUMN `revision_stats_typing` `revision_stats_typing_min`  int(11) NOT NULL DEFAULT 0 AFTER `payable_rates`,
CHANGE COLUMN `revision_stats_translations` `revision_stats_translations_min`  int(11) NOT NULL DEFAULT 0 AFTER `revision_stats_typing_min`,
CHANGE COLUMN `revision_stats_terminology` `revision_stats_terminology_min`  int(11) NOT NULL DEFAULT 0 AFTER `revision_stats_translations_min`,
CHANGE COLUMN `revision_stats_language_quality` `revision_stats_language_quality_min`  int(11) NOT NULL DEFAULT 0 AFTER `revision_stats_terminology_min`,
CHANGE COLUMN `revision_stats_style` `revision_stats_style_min`  int(11) NOT NULL DEFAULT 0 AFTER `revision_stats_language_quality_min`,
ADD COLUMN `revision_stats_typing_maj` int(11) NOT NULL DEFAULT 0 AFTER `revision_stats_style_min`,
ADD COLUMN `revision_stats_translations_maj` int(11) NOT NULL DEFAULT 0 AFTER `revision_stats_typing_maj`,
ADD COLUMN `revision_stats_terminology_maj` int(11) NOT NULL DEFAULT 0 AFTER `revision_stats_translations_maj`,
ADD COLUMN `revision_stats_language_quality_maj` int(11) NOT NULL DEFAULT 0 AFTER `revision_stats_terminology_maj`,
ADD COLUMN `revision_stats_style_maj` int(11) NOT NULL DEFAULT 0 AFTER `revision_stats_language_quality_maj`;

ALTER TABLE `converters`
ADD COLUMN `cpu_weight`  int(11) NOT NULL DEFAULT 1 AFTER `ip_converter`;

CREATE TABLE `segment_revisions` (
`id_job`  bigint(20) NOT NULL ,
`id_segment`  bigint(20) NOT NULL ,
`original_translation`  text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT 'The original translation before revisions.' ,
`err_typing`  varchar(512) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ,
`err_translation`  varchar(512) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ,
`err_terminology`  varchar(512) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ,
`err_quality`  varchar(512) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ,
`err_style`  varchar(512) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ,
PRIMARY KEY (`id_job`, `id_segment`),
INDEX `segm_key` (`id_segment`, `id_job`) USING BTREE
)
ENGINE=InnoDB
DEFAULT CHARACTER SET=utf8 COLLATE=utf8_general_ci;

ALTER TABLE segment_revisions
CHANGE COLUMN `err_quality` `err_language`  varchar(512) NOT NULL;

USE matecat_conversions_log;
ALTER TABLE `failed_conversions_log`
ADD COLUMN `file_size`  int(11) NOT NULL DEFAULT 0 AFTER `path_backup`,
ADD COLUMN `conversion_time`  decimal(11,7) NOT NULL DEFAULT 0.0000000 AFTER `status`;
