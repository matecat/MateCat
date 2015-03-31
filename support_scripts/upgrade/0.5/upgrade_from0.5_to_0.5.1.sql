USE matecat;

ALTER TABLE `engines`
  DROP COLUMN `gloss_get_relative_url`,
  DROP COLUMN `gloss_set_relative_url`,
  DROP COLUMN `gloss_update_relative_url`,
  DROP COLUMN `gloss_delete_relative_url`,
  DROP COLUMN `tmx_import_relative_url`,
  DROP COLUMN `tmx_status_relative_url`,
  ADD COLUMN `uid`  int(11) UNSIGNED NULL DEFAULT NULL AFTER `active`,
  ADD INDEX uid_idx(uid),
  MODIFY COLUMN penalty int(11) NOT NULL DEFAULT '14',
  MODIFY COLUMN `extra_parameters` varchar(2048) NOT NULL DEFAULT '{}',
  ADD COLUMN `class_load` varchar(255) NULL DEFAULT NULL AFTER `others`;

CREATE UNIQUE INDEX `id_UNIQUE` ON `converters`(`id`) USING BTREE ;

ALTER TABLE `jobs`
  ADD INDEX `status_owner_idx` (`status_owner`) USING BTREE,
  ADD INDEX `status_idx` (`status`) USING BTREE,
  ADD INDEX `create_date_idx` (`create_date`) USING BTREE ;

ALTER TABLE `memory_keys`
  ADD COLUMN `deleted`  int(11) NULL DEFAULT 0 AFTER `update_date`;

ALTER TABLE `original_files_map`
  DROP INDEX `creation_date`,
  DROP INDEX `source`,
  DROP INDEX `target`;