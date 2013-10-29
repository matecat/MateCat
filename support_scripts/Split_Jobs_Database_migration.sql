ALTER TABLE `matecat_local`.`jobs` 
    CHANGE COLUMN `id` `id` BIGINT NOT NULL AUTO_INCREMENT,
    CHANGE COLUMN `password` `password` VARCHAR(45) NOT NULL,
    ADD COLUMN `job_first_segment` BIGINT UNSIGNED NOT NULL  AFTER `id_project` , 
    ADD COLUMN `job_last_segment` BIGINT UNSIGNED NOT NULL  AFTER `job_first_segment` 
    , ADD UNIQUE INDEX `primary_id_pass` (`id` ASC, `password` ASC) 
    , DROP PRIMARY KEY ;

ALTER TABLE `matecat_local`.`files` 
    CHANGE COLUMN `id` `id` BIGINT NOT NULL AUTO_INCREMENT  ;

ALTER TABLE `matecat_local`.`segments`  
    CHANGE COLUMN `id_file` `id_file` BIGINT NOT NULL;

ALTER TABLE `matecat_local`.`segment_translations`
    CHANGE COLUMN `id_segment` `id_segment` BIGINT NOT NULL, 
    CHANGE COLUMN `id_job` `id_job` BIGINT NOT NULL;

UPDATE jobs, files_job
  SET 
  jobs.job_first_segment = files_job.id_segment_start,
  jobs.job_last_segment = files_job.id_segment_end
  WHERE files_job.id_job = jobs.id;


-- ALTER TABLE `segment_translations`
-- ADD COLUMN `mt_qe` FLOAT(19,14) NOT NULL DEFAULT 0
-- AFTER `suggestion_position` ;

