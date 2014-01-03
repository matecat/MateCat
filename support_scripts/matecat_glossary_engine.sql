ALTER TABLE `engines`
ADD COLUMN `gloss_get_relative_url` VARCHAR(100) NULL  AFTER `delete_relative_url` ,
ADD COLUMN `gloss_set_relative_url` VARCHAR(100) NULL  AFTER `gloss_get_relative_url` ,
ADD COLUMN `gloss_delete_relative_url` VARCHAR(100) NULL  AFTER `gloss_set_relative_url`
;

ALTER TABLE `engines`
ADD COLUMN `gloss_update_relative_url` VARCHAR(100) NULL DEFAULT NULL  AFTER `gloss_set_relative_url`
;

UPDATE `engines`
SET `gloss_get_relative_url`='glossary/get',
    `gloss_set_relative_url`='glossary/set',
    `gloss_delete_relative_url`='glossary/delete',
    `gloss_update_relative_url`='glossary/update'
WHERE `id`='1';

