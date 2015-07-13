CREATE SCHEMA `matecat_conversions_log` DEFAULT CHARACTER SET utf8 ;

USE matecat_conversions_log ;

CREATE  TABLE `failed_conversions_log` (
  `ip_machine` VARCHAR(15) NOT NULL ,
  `ip_client` VARCHAR(15) NOT NULL DEFAULT 'unknown' ,
  `path_backup` VARCHAR(512) NOT NULL ,
  `direction` VARCHAR(5) NOT NULL DEFAULT 'fw' COMMENT 'Directions: fw (forward), bw (backward)' ,
  `error_message` VARCHAR(255) NULL ,
  `conversion_date` TIMESTAMP DEFAULT NOW() ON UPDATE CURRENT_TIMESTAMP,
  `src_lang` VARCHAR(15) NULL DEFAULT NULL ,
  `trg_lang` VARCHAR(15) NULL DEFAULT NULL ,
  INDEX `ip_machine_idx` (`ip_machine` ASC) ,
  INDEX `direction_idx` (`direction` ASC) ,
  INDEX `path_backup_idx` (`path_backup` ASC) ,
  INDEX `src_lang_idx` (`src_lang` ASC) ,
  INDEX `trg_lang_idx` (`trg_lang` ASC) ,
  INDEX `conversion_date_idx` (`conversion_date` ASC) )
ENGINE = MyISAM
DEFAULT CHARACTER SET = utf8
COMMENT = 'Conversion Failure Log';

GRANT ALL ON matecat_conversions_log.* TO 'matecat'@'localhost' IDENTIFIED BY 'matecat01';
FLUSH PRIVILEGES;


-- GRANT ALL ON matecat_conversions_log.* TO 'matecat_user'@'localhost' IDENTIFIED BY 'matecat_user';
-- GRANT ALL ON matecat_conversions_log.* TO 'matecat_user'@'10.3%' IDENTIFIED BY 'matecat_user';
-- FLUSH PRIVILEGES;
