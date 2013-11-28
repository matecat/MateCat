ALTER TABLE `matecat_local`.`jobs` 
    CHANGE COLUMN `id` `id` BIGINT NOT NULL AUTO_INCREMENT,
    CHANGE COLUMN `password` `password` VARCHAR(45) NOT NULL,
    ADD COLUMN `job_first_segment` BIGINT UNSIGNED NOT NULL  AFTER `id_project` , 
    ADD COLUMN `job_last_segment` BIGINT UNSIGNED NOT NULL  AFTER `job_first_segment` 
    , ADD UNIQUE INDEX `primary_id_pass` (`id` ASC, `password` ASC) 
    , ADD INDEX `first_last_segment_idx` (`job_first_segment` ASC, `job_last_segment` ASC)
    , DROP PRIMARY KEY ;

ALTER TABLE `matecat_local`.`files` 
    CHANGE COLUMN `id` `id` BIGINT NOT NULL AUTO_INCREMENT  ;

ALTER TABLE `matecat_local`.`segments`  
    CHANGE COLUMN `id_file` `id_file` BIGINT NOT NULL;

ALTER TABLE `matecat_local`.`segment_translations`
    CHANGE COLUMN `id_segment` `id_segment` BIGINT NOT NULL, 
    CHANGE COLUMN `id_job` `id_job` BIGINT NOT NULL;


UPDATE  jobs AS job1
  INNER JOIN
  (
    SELECT MIN( segments.id ) as min, MAX(segments.id) as max, _job_.id
      FROM jobs _job_
      JOIN files_job ON files_job.id_job = _job_.id
      JOIN segments ON segments.id_file = files_job.id_file
      WHERE files_job.id_file IN ( 
        SELECT id_file FROM files_job WHERE id_job = _job_.id
      )
      group by _job_.id
  ) AS job2 ON job1.id = job2.id
SET 
  job1.job_first_segment = job2.min,
  job1.job_last_segment = job2.max
WHERE job1.job_last_segment = 0 OR job1.job_last_segment IS NULL

-- 
-- ALTER TABLE `segment_translations`
-- ADD COLUMN `mt_qe` FLOAT(19,14) NOT NULL DEFAULT 0
-- AFTER `suggestion_position` ;

