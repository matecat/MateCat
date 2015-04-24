USE matecat;

ALTER TABLE `projects`
ADD COLUMN pretranslate_100 int(1) DEFAULT 0

ALTER TABLE `memory_keys` 
ADD INDEX `creation_date` (`creation_date` ASC),
ADD INDEX `update_date` (`update_date` ASC),

