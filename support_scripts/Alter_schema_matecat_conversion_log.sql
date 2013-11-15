ALTER TABLE `matecat_conversions_log`.`failed_conversions_log`
ADD COLUMN `status` VARCHAR(2) NOT NULL DEFAULT 'ko'  AFTER `trg_lang`,
ADD INDEX `status_idx` (`status` ASC)
 ;