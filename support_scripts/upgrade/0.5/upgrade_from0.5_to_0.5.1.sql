USE matecat;

ALTER TABLE `engines`
DROP COLUMN `gloss_get_relative_url`,
DROP COLUMN `gloss_set_relative_url`,
DROP COLUMN `gloss_update_relative_url`,
DROP COLUMN `gloss_delete_relative_url`,
DROP COLUMN `tmx_import_relative_url`,
DROP COLUMN `tmx_status_relative_url`,
ADD COLUMN `uid`  int(11) UNSIGNED NULL DEFAULT NULL AFTER `active`;