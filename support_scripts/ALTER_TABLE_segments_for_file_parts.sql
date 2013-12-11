ALTER TABLE `matecat_local`.`segments`
ADD COLUMN  `id_file_part` BIGINT(20) NULL DEFAULT NULL  AFTER `id_file` ;

CREATE  TABLE `matecat_local`.`file_parts` (
  `id` BIGINT NOT NULL AUTO_INCREMENT ,
  `id_project` BIGINT NOT NULL ,
  `id_file` BIGINT NOT NULL ,
  `part_filename` VARCHAR(1024) NOT NULL ,
  `serialized_reference_meta` VARCHAR(1024) NULL,
  `serialized_reference_binaries` LONGBLOB NULL ,
  PRIMARY KEY (`id`) ,
  INDEX `id_file` (`id_file` ASC) );
