/*
Navicat MySQL Data Transfer

Source Server         : LocalHost
Source Server Version : 50540
Source Host           : localhost:3306
Source Database       : matecat

Target Server Type    : MYSQL
Target Server Version : 50540
File Encoding         : 65001

Date: 2014-10-22 10:51:46
*/

DROP SCHEMA IF EXISTS `unittest_matecat_local`;
CREATE SCHEMA `unittest_matecat_local` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `unittest_matecat_local`;


SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for converters
-- ----------------------------
DROP TABLE IF EXISTS `converters`;
CREATE TABLE `converters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_converter` varchar(45) NOT NULL,
  `ip_storage` varchar(45) NOT NULL,
  `ip_machine_host` varchar(45) NOT NULL,
  `machine_host_user` varchar(45) NOT NULL,
  `machine_host_pass` varchar(45) NOT NULL,
  `instance_name` varchar(45) NOT NULL,
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status_active` tinyint(4) NOT NULL DEFAULT '1',
  `status_offline` tinyint(4) NOT NULL DEFAULT '0',
  `status_reboot` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_UNIQUE` (`id`),
  UNIQUE KEY `ip_converter_UNIQUE` (`ip_converter`),
  UNIQUE KEY `ip_storage_UNIQUE` (`ip_storage`),
  KEY `status_active` (`status_active`),
  KEY `status_offline` (`status_offline`),
  KEY `status_reboot` (`status_reboot`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for converters_log
-- ----------------------------
DROP TABLE IF EXISTS `converters_log`;
CREATE TABLE `converters_log` (
  `id_log` int(11) NOT NULL AUTO_INCREMENT,
  `id_converter` int(11) NOT NULL,
  `check_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `test_passed` tinyint(4) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id_log`),
  KEY `timestamp_idx` (`check_time`),
  KEY `outcome_idx` (`test_passed`),
  KEY `id_converter_idx` (`id_converter`)
) ENGINE=InnoDB AUTO_INCREMENT=1050319 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for engines
-- ----------------------------
DROP TABLE IF EXISTS `engines`;
CREATE TABLE `engines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) DEFAULT 'no_name_engine',
  `type` varchar(45) NOT NULL DEFAULT 'MT',
  `description` text,
  `base_url` varchar(200) NOT NULL,
  `translate_relative_url` varchar(100) DEFAULT 'get',
  `contribute_relative_url` varchar(100) DEFAULT NULL,
  `delete_relative_url` varchar(100) DEFAULT NULL,
  `gloss_get_relative_url` varchar(100) DEFAULT NULL,
  `gloss_set_relative_url` varchar(100) DEFAULT NULL,
  `gloss_update_relative_url` varchar(100) DEFAULT NULL,
  `gloss_delete_relative_url` varchar(100) DEFAULT NULL,
  `tmx_import_relative_url` varchar(100) DEFAULT NULL,
  `tmx_status_relative_url` varchar(100) DEFAULT NULL,
  `extra_parameters` text,
  `google_api_compliant_version` varchar(45) DEFAULT NULL COMMENT 'credo sia superfluo',
  `penalty` int(11) DEFAULT '0',
  `active` tinyint(4) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `type` (`type`),
  KEY `active_idx` (`active`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8;

LOCK TABLES `engines` WRITE;
/*!40000 ALTER TABLE `engines` DISABLE KEYS */;
INSERT INTO `engines` VALUES (0,'NONE - PLACEHOLDER','NONE','No MT','','',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,100,1),(1,'MyMemory (All Pairs)','TM','MyMemory: next generation Translation Memory technology','http://api.mymemory.translated.net','get','set','delete','glossary/get','glossary/set','glossary/update','glossary/delete','tmx/import','tmx/status',NULL,'1',0,1),(2,'FBK Legal (EN->IT) - Ad.','MT','FBK (EN->IT) Moses Legal engine','http://hlt-services2.fbk.eu:8888','translate','update',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2',14,1),(3,'LIUM-IT (EN->DE)','MT','Lium (EN->FR) Moses Information Technology engine','http://193.52.29.52:8001','translate',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2',14,1),(4,'FBK Legal (EN>FR) - Ad.','MT','FBK (EN->FR) Moses Legal engine','http://hlt-services2.fbk.eu:8988','translate','update',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2',14,1),(5,'LIUM-LEGAL (EN->DE)','MT','Lium (EN->FR) Moses Legal engine','http://193.52.29.52:8002','translate',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,14,1),(6,'FBK TED (IT>EN)','MT','FBK (IT->EN) Moses Information Technology engine','http://hlt-services2.fbk.eu:8788','translate','update',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2',14,1),(29,'MyMemory (All Pairs) - proxy','TM','MyMemory: next generation Translation Memory technology','http://api.mymemory.translated.net','get','set','delete','glossary/get','glossary/set','glossary/update','glossary/delete',NULL,NULL,NULL,'1',0,1),(30,'FBK Legal (EN->IT) AdaTest01','MT','FBK Legal (EN->IT) AdaTest01 - Used for field test for online learning - March 2014','http://hlt-services2.fbk.eu:8721','translate','update',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2',14,1),(31,'FBK Legal (EN->IT) AdaTest02','MT','FBK Legal (EN->IT) AdaTest02 - Used for field test for online learning - March 2014','http://hlt-services2.fbk.eu:8722','translate','update',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2',14,1),(32,'FBK Legal (EN->IT) AdaTest03','MT','FBK Legal (EN->IT) AdaTest03 - Used for field test for online learning - March 2014','http://hlt-services2.fbk.eu:8723','translate','update',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2',14,1),(33,'FBK Legal (EN->IT) AdaTest04','MT','FBK Legal (EN->IT) AdaTest04 - Used for field test for online learning - March 2014','http://hlt-services2.fbk.eu:8724','translate','update',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2',14,1),(34,'FBK Legal (EN>IT) StatTest','MT',NULL,'http://hlt-services2.fbk.eu:8720','translate','update',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2',14,1),(35,'FBK Legal (EN>FR) AdaTest01','MT','FBK Legal (EN>FR) AdaTest01 - Used for field test for online learning - April 2014','http://hlt-services2.fbk.eu:8521','translate','update',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2',14,1),(36,'FBK Legal (EN>FR) AdaTest02','MT','FBK Legal (EN>FR) AdaTest02 - Used for field test for online learning - April 2014','http://hlt-services2.fbk.eu:8522','translate','update',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2',14,1),(37,'FBK Legal (EN>FR) AdaTest03','MT','FBK Legal (EN>FR) AdaTest03 - Used for field test for online learning - April 2014','http://hlt-services2.fbk.eu:8523','translate','update',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2',14,1),(38,'FBK Legal (EN>FR) AdaTest04','MT','FBK Legal (EN>FR) AdaTest01 - Used for field test for online learning - April 2014','http://hlt-services2.fbk.eu:8524','translate','update',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2',14,1);
/*!40000 ALTER TABLE `engines` ENABLE KEYS */;
UNLOCK TABLES;

-- ----------------------------
-- Table structure for file_references
-- ----------------------------
DROP TABLE IF EXISTS `file_references`;
CREATE TABLE `file_references` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_project` bigint(20) NOT NULL,
  `id_file` bigint(20) NOT NULL,
  `part_filename` varchar(1024) NOT NULL,
  `serialized_reference_meta` varchar(1024) DEFAULT NULL,
  `serialized_reference_binaries` longblob,
  PRIMARY KEY (`id`),
  KEY `id_file` (`id_file`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for files
-- ----------------------------
DROP TABLE IF EXISTS `files`;
CREATE TABLE `files` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_project` int(11) NOT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `source_language` varchar(45) NOT NULL,
  `mime_type` varchar(45) DEFAULT NULL,
  `xliff_file` longblob,
  `sha1_original_file` varchar(100) DEFAULT NULL,
  `original_file` longblob,
  PRIMARY KEY (`id`),
  KEY `id_project` (`id_project`),
  KEY `sha1` (`sha1_original_file`) USING HASH,
  KEY `filename` (`filename`)
) ENGINE=MyISAM AUTO_INCREMENT=55796 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for files_job
-- ----------------------------
DROP TABLE IF EXISTS `files_job`;
CREATE TABLE `files_job` (
  `id_job` int(11) NOT NULL,
  `id_file` int(11) NOT NULL,
  `assign_date` datetime DEFAULT NULL,
  `t_delivery_date` datetime DEFAULT NULL,
  `t_a_delivery_date` datetime DEFAULT NULL,
  `id_segment_start` int(11) DEFAULT NULL,
  `id_segment_end` int(11) DEFAULT NULL,
  `status_analisys` varchar(50) DEFAULT 'NEW' COMMENT 'NEW\nIN PROGRESS\nDONE',
  PRIMARY KEY (`id_job`,`id_file`),
  KEY `id_file` (`id_file`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for jobs
-- ----------------------------
DROP TABLE IF EXISTS `jobs`;
CREATE TABLE `jobs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `password` varchar(45) NOT NULL,
  `id_project` int(11) NOT NULL,
  `job_first_segment` bigint(20) unsigned NOT NULL,
  `job_last_segment` bigint(20) unsigned NOT NULL,
  `id_translator` varchar(100) NOT NULL DEFAULT 'generic_translator',
  `tm_keys` text NOT NULL,
  `job_type` varchar(45) DEFAULT NULL,
  `source` varchar(45) DEFAULT NULL,
  `target` varchar(45) DEFAULT NULL,
  `c_delivery_date` datetime DEFAULT NULL,
  `c_a_delivery_date` datetime DEFAULT NULL,
  `id_job_to_revise` int(11) DEFAULT NULL,
  `last_opened_segment` int(11) DEFAULT NULL,
  `id_tms` int(11) DEFAULT '1',
  `id_mt_engine` int(11) DEFAULT '1',
  `create_date` datetime NOT NULL,
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `disabled` tinyint(4) NOT NULL,
  `owner` varchar(100) DEFAULT NULL,
  `status_owner` varchar(100) NOT NULL DEFAULT 'active',
  `status_translator` varchar(100) DEFAULT NULL,
  `status` varchar(15) NOT NULL DEFAULT 'active',
  `completed` bit(1) NOT NULL DEFAULT b'0',
  `new_words` float(10,2) NOT NULL DEFAULT '0.00',
  `draft_words` float(10,2) NOT NULL DEFAULT '0.00',
  `translated_words` float(10,2) NOT NULL DEFAULT '0.00',
  `approved_words` float(10,2) NOT NULL DEFAULT '0.00',
  `rejected_words` float(10,2) NOT NULL DEFAULT '0.00',
  UNIQUE KEY `primary_id_pass` (`id`,`password`),
  KEY `id_job_to_revise` (`id_job_to_revise`),
  KEY `id_project` (`id_project`) USING BTREE,
  KEY `owner` (`owner`),
  KEY `id_translator` (`id_translator`),
  KEY `first_last_segment_idx` (`job_first_segment`,`job_last_segment`),
  KEY `id` (`id`) USING BTREE,
  KEY `password` (`password`),
  KEY `source` (`source`),
  KEY `target` (`target`)
) ENGINE=InnoDB AUTO_INCREMENT=41609 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for memory_keys
-- ----------------------------
DROP TABLE IF EXISTS `memory_keys`;
CREATE TABLE `memory_keys` (
  `uid` bigint(20) NOT NULL,
  `key_value` varchar(45) NOT NULL,
  `key_name` varchar(512) DEFAULT NULL,
  `key_tm` tinyint(1) DEFAULT '1',
  `key_glos` tinyint(1) DEFAULT '1',
  `creation_date` timestamp NULL DEFAULT NULL,
  `update_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uid`,`key_value`),
  KEY `uid_idx` (`uid`),
  KEY `key_value_idx` (`key_value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for memory_keys_ref
-- ----------------------------
DROP TABLE IF EXISTS `memory_keys_ref`;
CREATE TABLE `memory_keys_ref` (
  `gid` bigint(20) NOT NULL DEFAULT '0',
  `uid` bigint(20) NOT NULL,
  `owner_uid` bigint(20) NOT NULL,
  `key_value` varchar(45) NOT NULL,
  `key_name` varchar(512) DEFAULT NULL,
  `key_tm` tinyint(1) DEFAULT '1',
  `key_glos` tinyint(1) DEFAULT '1',
  `read_grants` tinyint(1) NOT NULL DEFAULT '1',
  `write_grants` tinyint(1) NOT NULL DEFAULT '1',
  `creation_date` timestamp NULL DEFAULT NULL,
  `grant_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`gid`,`uid`,`key_value`),
  KEY `gid_idx` (`gid`),
  KEY `uid_idx` (`uid`),
  KEY `owner_uid_idx` (`owner_uid`),
  KEY `key_value_idx` (`key_value`),
  KEY `gid_uid_idx` (`gid`,`uid`),
  CONSTRAINT `gid_uid_frk` FOREIGN KEY (`gid`, `uid`) REFERENCES `user_groups_ref` (`gid`, `uid`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for notifications
-- ----------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `id_comment` int(11) NOT NULL,
  `id_translator` varchar(100) CHARACTER SET latin1 NOT NULL,
  `status` varchar(45) CHARACTER SET latin1 DEFAULT 'UNREAD',
  PRIMARY KEY (`id`),
  KEY `id_comment` (`id_comment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for original_files_map
-- ----------------------------
DROP TABLE IF EXISTS `original_files_map`;
CREATE TABLE `original_files_map` (
  `sha1` varchar(100) NOT NULL,
  `source` varchar(50) NOT NULL,
  `target` varchar(50) NOT NULL,
  `deflated_file` longblob,
  `deflated_xliff` longblob,
  `creation_date` date DEFAULT NULL,
  PRIMARY KEY (`sha1`,`source`,`target`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for projects
-- ----------------------------
DROP TABLE IF EXISTS `projects`;
CREATE TABLE `projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `password` varchar(45) DEFAULT NULL,
  `id_customer` varchar(45) NOT NULL,
  `name` varchar(200) DEFAULT 'project',
  `create_date` datetime NOT NULL,
  `id_engine_tm` int(11) DEFAULT NULL,
  `id_engine_mt` int(11) DEFAULT NULL,
  `status_analysis` varchar(50) DEFAULT 'NOT_READY_TO_ANALYZE',
  `fast_analysis_wc` double(20,2) DEFAULT '0.00',
  `tm_analysis_wc` double(20,2) DEFAULT '0.00',
  `standard_analysis_wc` double(20,2) DEFAULT '0.00',
  `remote_ip_address` varchar(45) DEFAULT 'UNKNOWN',
  `for_debug` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `id_customer` (`id_customer`),
  KEY `status_analysis` (`status_analysis`),
  KEY `for_debug` (`for_debug`),
  KEY `remote_ip_address` (`remote_ip_address`),
  KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=34502 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for segment_translations
-- ----------------------------
DROP TABLE IF EXISTS `segment_translations`;
CREATE TABLE `segment_translations` (
  `id_segment` bigint(20) NOT NULL,
  `id_job` bigint(20) NOT NULL,
  `segment_hash` varchar(45) NOT NULL,
  `autopropagated_from` bigint(20) DEFAULT NULL,
  `status` varchar(45) DEFAULT 'NEW',
  `translation` text,
  `translation_date` datetime DEFAULT NULL,
  `time_to_edit` int(11) NOT NULL DEFAULT '0',
  `match_type` varchar(45) DEFAULT 'NEW',
  `context_hash` blob,
  `eq_word_count` double(20,2) DEFAULT NULL,
  `standard_word_count` double(20,2) DEFAULT NULL,
  `suggestions_array` text,
  `suggestion` text,
  `suggestion_match` int(11) DEFAULT NULL,
  `suggestion_source` varchar(45) DEFAULT NULL,
  `suggestion_position` int(11) DEFAULT NULL,
  `mt_qe` float(19,14) NOT NULL DEFAULT '0.00000000000000',
  `tm_analysis_status` varchar(50) DEFAULT 'UNDONE',
  `locked` tinyint(4) DEFAULT '0',
  `warning` tinyint(4) NOT NULL DEFAULT '0',
  `serialized_errors_list` varchar(512) DEFAULT NULL,
  PRIMARY KEY (`id_segment`,`id_job`),
  KEY `status` (`status`),
  KEY `id_job` (`id_job`),
  KEY `translation_date` (`translation_date`) USING BTREE,
  KEY `tm_analysis_status` (`tm_analysis_status`) USING BTREE,
  KEY `locked` (`locked`) USING BTREE,
  KEY `id_segment` (`id_segment`) USING BTREE,
  KEY `warning` (`warning`),
  KEY `segment_hash` (`segment_hash`) USING HASH,
  KEY `auto_idx` (`autopropagated_from`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for segments
-- ----------------------------
DROP TABLE IF EXISTS `segments`;
CREATE TABLE `segments` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_file` bigint(20) NOT NULL,
  `id_file_part` bigint(20) DEFAULT NULL,
  `internal_id` varchar(100) DEFAULT NULL,
  `xliff_mrk_id` varchar(70) DEFAULT NULL,
  `xliff_ext_prec_tags` text,
  `xliff_mrk_ext_prec_tags` text,
  `segment` text,
  `segment_hash` varchar(45) NOT NULL,
  `xliff_mrk_ext_succ_tags` text,
  `xliff_ext_succ_tags` text,
  `raw_word_count` double(20,2) DEFAULT NULL,
  `show_in_cattool` tinyint(4) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `id_file` (`id_file`) USING BTREE,
  KEY `internal_id` (`internal_id`) USING BTREE,
  KEY `mrk_id` (`xliff_mrk_id`) USING BTREE,
  KEY `show_in_cat` (`show_in_cattool`) USING BTREE,
  KEY `raw_word_count` (`raw_word_count`) USING BTREE,
  KEY `id_file_part_idx` (`id_file_part`),
  KEY `segment_hash` (`segment_hash`) USING HASH COMMENT 'MD5 hash of segment content'
) ENGINE=InnoDB AUTO_INCREMENT=20863570 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for segments_comments
-- ----------------------------
DROP TABLE IF EXISTS `segments_comments`;
CREATE TABLE `segments_comments` (
  `id` int(11) NOT NULL,
  `id_segment` int(11) NOT NULL,
  `comment` text,
  `create_date` datetime DEFAULT NULL,
  `created_by` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_segment` (`id_segment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for translators
-- ----------------------------
DROP TABLE IF EXISTS `translators`;
CREATE TABLE `translators` (
  `username` varchar(100) NOT NULL,
  `email` varchar(45) DEFAULT NULL,
  `password` varchar(45) DEFAULT NULL,
  `first_name` varchar(45) DEFAULT NULL,
  `last_name` varchar(45) DEFAULT NULL,
  `mymemory_api_key` varchar(50) NOT NULL,
  PRIMARY KEY (`username`),
  KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for user_groups_ref
-- ----------------------------
DROP TABLE IF EXISTS `user_groups_ref`;
CREATE TABLE `user_groups_ref` (
  `gid` bigint(20) NOT NULL,
  `uid` bigint(20) NOT NULL,
  `group_name` varchar(512) DEFAULT NULL,
  UNIQUE KEY `guid_idx` (`gid`,`uid`,`group_name`(255)),
  KEY `uid_idx` (`uid`),
  KEY `gid_idx` (`gid`),
  KEY `name_idx` (`group_name`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for users
-- ----------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `uid` bigint(20) NOT NULL AUTO_INCREMENT,
  `email` varchar(50) NOT NULL,
  `salt` varchar(50) NOT NULL,
  `pass` varchar(50) NOT NULL,
  `create_date` datetime NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `api_key` varchar(100) NOT NULL,
  PRIMARY KEY (`uid`),
  UNIQUE KEY `email` (`email`),
  KEY `api_key` (`api_key`)
) ENGINE=InnoDB AUTO_INCREMENT=124 DEFAULT CHARSET=utf8;

LOCK TABLES `users` WRITE;
INSERT INTO `users` VALUES ('123', 'usermail@userdomain.net', '4a815396c2b7e00', '04fbd4c01889ac78780367c9ba09feb0ef90105d', '2013-10-02 15:54:47', 'Mario', 'Rossi', '');
UNLOCK TABLES;

--
-- Current Database: `matecat_analysis`
--
DROP SCHEMA IF EXISTS `unittest_matecat_analysis_local`;
CREATE DATABASE /*!32312 IF NOT EXISTS*/ `unittest_matecat_analysis_local` /*!40100 DEFAULT CHARACTER SET latin1 */;

USE `unittest_matecat_analysis_local`;

--
-- Table structure for table `segment_translations_analysis_queue`
--

DROP TABLE IF EXISTS `segment_translations_analysis_queue`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `segment_translations_analysis_queue` (
  `id_segment` int(11) NOT NULL,
  `id_job` int(11) NOT NULL,
  `locked` int(11) DEFAULT '0',
  `pid` int(11) DEFAULT NULL,
  `date_insert` datetime DEFAULT NULL,
  PRIMARY KEY (`id_segment`,`id_job`),
  KEY `locked` (`locked`) USING BTREE,
  KEY `pid` (`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;


-- Dump completed on 2013-10-09 16:04:19
-- Drop user if already Exista
GRANT USAGE ON *.* TO 'unt_matecat_user'@'localhost';
DROP USER 'unt_matecat_user'@'localhost';
CREATE USER 'unt_matecat_user'@'localhost' IDENTIFIED BY 'unt_matecat_user';
GRANT ALL ON unittest_matecat_local.* TO 'unt_matecat_user'@'localhost' IDENTIFIED BY 'unt_matecat_user';
GRANT ALL ON unittest_matecat_analysis_local.* TO 'matecat'@'localhost';
FLUSH PRIVILEGES;