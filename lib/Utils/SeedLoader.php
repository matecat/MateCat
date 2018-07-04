<?php

// This class is a quick hack, to be improved and expanded
//
class SeedLoader {
    public $database;

    function __construct( $database ) {
        $this->database = $database;
    }

    public function getSeedSql() {
        $out = $this->getEnginesSql();

        return $out;
    }

    public function getEnginesSql() {
        return <<<EOF

INSERT INTO `engines` VALUES (NULL,'NONE','NONE','No MT','','',NULL,NULL,NULL,'{}','NONE','',NULL,100,0,NULL);
INSERT INTO `engines` VALUES (NULL,'MyMemory (All Pairs)','TM','Machine translation from Google Translate and Microsoft Translator.','http://api.mymemory.translated.net','get','set','update','delete','{\"gloss_get_relative_url\":\"glossary/get\",\"gloss_set_relative_url\":\"glossary/set\",\"gloss_update_relative_url\":\"glossary/update\",\"glossary_import_relative_url\":\"glossary/import\",\"glossary_export_relative_url\":\"glossary/export\",\"gloss_delete_relative_url\":\"glossary/delete\",\"tmx_import_relative_url\":\"tmx/import\",\"tmx_status_relative_url\":\"tmx/status\",\"tmx_export_create_url\":\"tmx/export/create\",\"tmx_export_check_url\":\"tmx/export/check\",\"tmx_export_download_url\":\"tmx/export/download\",\"tmx_export_list_url\":\"tmx/export/list\",\"tmx_export_email_url\":\"tmx/export/create\",\"api_key_create_user_url\":\"createranduser\",\"api_key_check_auth_url\":\"authkey\",\"analyze_url\":\"analyze\",\"detect_language_url\":\"langdetect.php\"}','MyMemory','{}','1',0,1,NULL);

UPDATE engines SET id = 0 WHERE name = 'NONE' ;
UPDATE engines SET id = 1 WHERE name = 'MyMemory (All Pairs)' ;

-- populate sequences
INSERT INTO sequences ( id_segment, id_project ) VALUES ( IFNULL( (SELECT MAX(id) + 1 FROM segments), 1), IFNULL( (SELECT MAX(id) + 1 FROM projects), 1) );

#Create the user 'matecat'@'%'
CREATE USER 'matecat'@'%' IDENTIFIED BY 'matecat01';

# Grants for 'matecat'@'%'
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE, SHOW VIEW ON `{$this->database->config['DB_DATABASE']}`.* TO 'matecat'@'%';

INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20150918101657', '2016-12-22 12:24:34', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20150921114813', '2016-12-22 12:24:35', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20150922152051', '2016-12-22 12:24:35', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20151001131124', '2016-12-22 12:24:35', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20151120150352', '2016-12-22 12:24:35', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20151123141623', '2016-12-22 12:24:35', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20151126093945', '2016-12-22 12:24:35', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20151204140144', '2016-12-22 12:24:35', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20151219181543', '2016-12-22 12:24:35', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20151229103454', '2016-12-22 12:24:35', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20160108101432', '2016-12-22 12:24:35', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20160115143225', '2016-12-22 12:24:35', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20160116085841', '2016-12-22 12:24:35', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20160120143540', '2016-12-22 12:24:36', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20160121170252', '2016-12-22 12:24:36', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20160124101801', '2016-12-22 12:24:36', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20160301134214', '2016-12-22 12:24:36', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20160311094715', '2016-12-22 12:24:36', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20160318130527', '2016-12-22 12:24:36', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20160329131606', '2016-12-22 12:24:36', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20160331142550', '2016-12-22 12:24:36', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20160331210238', '2016-12-22 12:24:36', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20160406102209', '2016-12-22 12:24:36', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20160408162842', '2016-12-22 12:24:36', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20160519093951', '2016-12-22 12:24:36', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20160524122147', '2016-12-22 12:24:36', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20160608130816', '2016-12-22 12:24:36', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20160613103347', '2016-12-22 12:24:36', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20160902141754', '2016-12-22 12:24:36', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20160909113520', '2016-12-22 12:24:36', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20160916105911', '2016-12-22 12:24:36', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20161027154703', '2016-12-22 12:24:36', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20161107080000', '2016-12-22 12:24:36', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20161107094229', '2016-12-22 12:24:36', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20161118144241', '2016-12-22 12:24:36', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20161122093431', '2016-12-22 12:24:36', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20161125145959', '2016-12-22 12:24:36', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20161207184244', '2016-12-22 12:24:36', '0000-00-00 00:00:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20161219160843', '2017-01-20 12:16:32', '2017-01-20 12:16:37' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20161223190349', '2016-12-23 20:29:57', '2016-12-23 20:29:57' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20161223191009', '2016-12-23 20:54:15', '2016-12-23 20:54:15' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20161223191509', '2016-12-23 21:04:14', '2016-12-23 21:04:14' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20161230151125', '2017-01-20 12:16:37', '2017-01-20 12:16:38' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20170113150724', '2017-02-01 18:50:56', '2017-02-01 18:50:56' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20170201160907', '2017-02-02 10:44:42', '2017-02-02 10:44:44' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20170202125007', '2017-02-15 12:16:07', '2017-02-15 12:16:09' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20170203120331', '2017-02-15 12:16:09', '2017-02-15 12:16:09' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20170203145956', '2017-02-15 12:16:09', '2017-02-15 12:16:10' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20170220193303', '2017-02-22 19:30:58', '2017-02-22 19:30:58' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20170227194426', '2017-02-27 20:46:26', '2017-02-27 20:46:27' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20170303175809', '2017-03-17 17:38:39', '2017-03-17 17:38:41' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20170306121226', '2017-03-13 19:28:56', '2017-03-13 19:29:00' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20170310111529', '2017-03-17 17:56:21', '2017-03-17 17:56:21' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20170313184235', '2017-03-17 17:47:22', '2017-03-17 17:47:22' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20170320191235', '2017-03-23 20:39:11', '2017-03-23 20:39:11' );
INSERT INTO phinxlog ( version, start_time, end_time ) VALUES( '20170405133041', '2017-04-05 15:37:42', '2017-04-05 15:37:42' );

EOF;

    }

    function loadEngines() {
        $this->database->execSql( $this->getSeedSql() );
    }

    function getConversionLogSchema(){

        $schemaCreation = <<<EOS

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

#Create the user 'matecat'@'%' ( even if already created )
# Grants for 'matecat'@'%'
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE, SHOW VIEW ON `matecat_conversions_log`.* TO 'matecat'@'%' IDENTIFIED BY 'matecat01';

EOS;

        return $schemaCreation;

    }

}
