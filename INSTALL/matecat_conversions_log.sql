CREATE SCHEMA `matecat_conversions_log` DEFAULT CHARACTER SET utf8 ;

USE matecat_conversions_log ;

CREATE TABLE conversions_log (
  id BIGINT NOT NULL AUTO_INCREMENT,
  time TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
  filters_address VARCHAR(21),
  filters_version VARCHAR(100),
  client_ip VARCHAR(15) NOT NULL,
  to_xliff TINYINT(1) NOT NULL COMMENT 'true for source-to-xliff conversions, false for xliff-to-target',
  source_file_ext VARCHAR(45) NOT NULL,
  source_file_name VARCHAR(255) NOT NULL,
  success TINYINT(1) NOT NULL,
  error_message VARCHAR(255),
  job_owner VARCHAR(100),
  job_id INT(11),
  job_pwd VARCHAR(45),
  source_file_id INT(11) COMMENT 'when to_xliff is false, this contains the SOURCE file\'s file_id, that you can easily find in "files" and "files_job" tables',
  source_file_sha1 VARCHAR(100) COMMENT 'when to_xliff is true, this is the sha1 of the sent file; when to_xliff is false, this is the "sha1_original_file" in the "file" table of the source file',
  source_lang VARCHAR(45) NOT NULL,
  target_lang VARCHAR(45) NOT NULL,
  segmentation VARCHAR(512),
  sent_file_size INT(11) NOT NULL COMMENT 'the number of actual bytes sent to the converter',
  conversion_time INT(11) NOT NULL COMMENT 'in milliseconds',

  PRIMARY KEY (id),
  KEY(time),
  KEY (filters_address),
  KEY (filters_version),
  KEY (client_ip),
  KEY (source_file_ext),
  KEY (job_owner),
  KEY (job_id),
  KEY (source_file_id),
  KEY (source_file_sha1),
  KEY (source_lang),
  KEY (target_lang)
)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8;