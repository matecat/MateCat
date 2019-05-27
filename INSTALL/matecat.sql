

INSERT INTO `engines` VALUES (10,'NONE','NONE','No MT','','',NULL,NULL,NULL,'{}','NONE','',NULL,100,0,NULL);
INSERT INTO `engines` VALUES (11,'MyMemory (All Pairs)','TM','Machine translation from Google Translate and Microsoft Translator.','http://api.mymemory.translated.net','get','set','update','delete',
                                 '{\"gloss_get_relative_url\":\"glossary/get\",\"gloss_set_relative_url\":\"glossary/set\",\"gloss_update_relative_url\":\"glossary/update\",\"glossary_import_relative_url\":\"glossary/import\",\"glossary_export_relative_url\":\"glossary/export\",\"gloss_delete_relative_url\":\"glossary/delete\",\"tmx_import_relative_url\":\"tmx/import\",\"tmx_status_relative_url\":\"tmx/status\",\"tmx_export_create_url\":\"tmx/export/create\",\"tmx_export_check_url\":\"tmx/export/check\",\"tmx_export_download_url\":\"tmx/export/download\",\"tmx_export_list_url\":\"tmx/export/list\",\"tmx_export_email_url\":\"tmx/export/create\",\"api_key_create_user_url\":\"createranduser\",\"api_key_check_auth_url\":\"authkey\",\"analyze_url\":\"analyze\",\"detect_language_url\":\"langdetect.php\"}','MyMemory','{}','1',0,1,NULL);

UPDATE engines SET id = 0 WHERE id = 10 ;
UPDATE engines SET id = 1 WHERE id = 11 ;

-- populate sequences
INSERT INTO sequences ( id_segment, id_project, id_dqf_project ) VALUES ( IFNULL( (SELECT MAX(id) + 1 FROM segments), 1), IFNULL( (SELECT MAX(id) + 1 FROM projects), 1), 1 );

#Create the user 'matecat'@'%' IF NOT EXISTS
-- CREATE USER 'matecat'@'%' IDENTIFIED BY 'matecat01';

# Grants for 'matecat'@'%'
GRANT SELECT, INSERT, UPDATE,DELETE, EXECUTE, SHOW VIEW ON `matecat`.* TO 'matecat'@'%'  IDENTIFIED BY 'matecat01';


CREATE SCHEMA IF NOT EXISTS `matecat_conversions_log` DEFAULT CHARACTER SET utf8 ;
USE matecat_conversions_log ;
CREATE TABLE IF NOT EXISTS conversions_log (
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

#Create the user 'matecat'@'%' ( even if already created )
# Grants for 'matecat'@'%'
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE, SHOW VIEW ON `matecat_conversions_log`.* TO 'matecat'@'%' IDENTIFIED BY 'matecat01';

GRANT DROP ON `matecat`.`jobs_stats` TO 'PEEWorker'@'%' IDENTIFIED BY 'matecat02';

USE `matecat`;

