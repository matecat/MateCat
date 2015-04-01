-- MySQL dump 10.13  Distrib 5.5.35, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: matecat
-- ------------------------------------------------------
-- Server version	5.5.35-0+wheezy1-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `matecat`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `matecat` /*!40100 DEFAULT CHARACTER SET utf8 */;

USE `matecat`;

--
-- Table structure for table `converters`
--

DROP TABLE IF EXISTS `converters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `converters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_converter` varchar(45) NOT NULL,
  `cpu_weight` int(11) NOT NULL DEFAULT '1',
  `ip_storage` varchar(45) NOT NULL,
  `ip_machine_host` varchar(45) NOT NULL,
  `machine_host_user` varchar(45) NOT NULL,
  `machine_host_pass` varchar(45) NOT NULL,
  `instance_name` varchar(45) NOT NULL,
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status_active` tinyint(4) NOT NULL DEFAULT '1',
  `status_offline` tinyint(4) NOT NULL DEFAULT '0',
  `status_reboot` tinyint(4) NOT NULL DEFAULT '0',
  `conversion_api_version` varchar(100) DEFAULT '2011',
  `segmentation_rule` varchar(512) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip_converter_UNIQUE` (`ip_converter`),
  UNIQUE KEY `ip_storage_UNIQUE` (`ip_storage`),
  UNIQUE KEY `id_UNIQUE` (`id`) USING BTREE,
  KEY `status_active` (`status_active`),
  KEY `status_offline` (`status_offline`),
  KEY `status_reboot` (`status_reboot`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `converters_log`
--

DROP TABLE IF EXISTS `converters_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `converters_log` (
  `id_log` int(11) NOT NULL AUTO_INCREMENT,
  `id_converter` int(11) NOT NULL,
  `check_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `test_passed` tinyint(4) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id_log`),
  KEY `timestamp_idx` (`check_time`),
  KEY `outcome_idx` (`test_passed`),
  KEY `id_converter_idx` (`id_converter`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `engines`
--

DROP TABLE IF EXISTS `engines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `engines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) DEFAULT 'no_name_engine',
  `type` varchar(45) NOT NULL DEFAULT 'MT',
  `description` text,
  `base_url` varchar(200) NOT NULL,
  `translate_relative_url` varchar(100) DEFAULT 'get',
  `contribute_relative_url` varchar(100) DEFAULT NULL,
  `delete_relative_url` varchar(100) DEFAULT NULL,
  `others` varchar(2048) NOT NULL DEFAULT '{}' COMMENT 'json key_value for api end points',
  `class_load` varchar(255) DEFAULT NULL,
  `extra_parameters` varchar(2048) NOT NULL DEFAULT '{}',
  `google_api_compliant_version` varchar(45) DEFAULT NULL COMMENT 'credo sia superfluo',
  `penalty` int(11) NOT NULL DEFAULT '14',
  `active` tinyint(4) NOT NULL DEFAULT '1',
  `uid` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `type` (`type`),
  KEY `active_idx` (`active`) USING BTREE,
  KEY `uid_idx` (`uid`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `engines` WRITE;
/*!40000 ALTER TABLE `engines` DISABLE KEYS */;
INSERT INTO `matecat`.`engines` (`id`, `name`, `type`, `description`, `base_url`, `translate_relative_url`, `contribute_relative_url`, `delete_relative_url`, `others`, `class_load`, `extra_parameters`, `google_api_compliant_version`, `penalty`, `active`, `uid`) VALUES ('0', 'NONE', 'NONE', 'No MT', '', '', NULL, NULL, '{}', 'NONE', '', NULL, '100', '0', NULL);
UPDATE `matecat`.`engines` SET `id` = '0' WHERE description = 'No MT';
INSERT INTO `matecat`.`engines` (`id`, `name`, `type`, `description`, `base_url`, `translate_relative_url`, `contribute_relative_url`, `delete_relative_url`, `others`, `class_load`, `extra_parameters`, `google_api_compliant_version`, `penalty`, `active`, `uid`) VALUES ('1', 'MyMemory (All Pairs)', 'TM', 'Machine translation from Google Translate and Microsoft Translator.', 'http://api.mymemory.translated.net', 'get', 'set', 'delete', '{\"gloss_get_relative_url\":\"glossary\\/get\",\"gloss_set_relative_url\":\"glossary\\/set\",\"gloss_update_relative_url\":\"glossary\\/update\",\"gloss_delete_relative_url\":\"glossary\\/delete\",\"tmx_import_relative_url\":\"tmx\\/import\",\"tmx_status_relative_url\":\"tmx\\/status\",\"tmx_export_create_url\":\"tmx\\/export\\/create\",\"tmx_export_check_url\":\"tmx\\/export\\/check\",\"tmx_export_download_url\":\"tmx\\/export\\/download\",\"tmx_export_list_url\":\"tmx\\/export\\/list\",\"api_key_create_user_url\":\"createranduser\",\"api_key_check_auth_url\":\"authkey\",\"analyze_url\":\"analyze\",\"detect_language_url\":\"langdetect.php\"}', 'MyMemory', '', '1', '0', '1', NULL);
/*!40000 ALTER TABLE `engines` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

--
-- Table structure for table `file_references`
--

DROP TABLE IF EXISTS `file_references`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `file_references` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_project` bigint(20) NOT NULL,
  `id_file` bigint(20) NOT NULL,
  `part_filename` varchar(1024) NOT NULL,
  `serialized_reference_meta` varchar(1024) DEFAULT NULL,
  `serialized_reference_binaries` longblob,
  PRIMARY KEY (`id`),
  KEY `id_file` (`id_file`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `files`
--

DROP TABLE IF EXISTS `files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `files` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_project` int(11) NOT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `source_language` varchar(45) NOT NULL,
  `mime_type` varchar(45) DEFAULT NULL,
  `xliff_file` longblob,
  `sha1_original_file` varchar(100) DEFAULT NULL,
  `original_file` longblob,
  `segmentation_rule` varchar(512) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_project` (`id_project`),
  KEY `sha1` (`sha1_original_file`) USING HASH,
  KEY `filename` (`filename`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `files_job`
--

DROP TABLE IF EXISTS `files_job`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `subject` varchar(100) DEFAULT 'general',
  `payable_rates` varchar(500) DEFAULT '{"NO_MATCH":100,"50%-74%":100,"75%-99%":60,"100%":30,"REPETITIONS":30,"INTERNAL":60,"MT":85}',
  `revision_stats_typing_min` int(11) NOT NULL DEFAULT '0',
  `revision_stats_translations_min` int(11) NOT NULL DEFAULT '0',
  `revision_stats_terminology_min` int(11) NOT NULL DEFAULT '0',
  `revision_stats_language_quality_min` int(11) NOT NULL DEFAULT '0',
  `revision_stats_style_min` int(11) NOT NULL DEFAULT '0',
  `revision_stats_typing_maj` int(11) NOT NULL DEFAULT '0',
  `revision_stats_translations_maj` int(11) NOT NULL DEFAULT '0',
  `revision_stats_terminology_maj` int(11) NOT NULL DEFAULT '0',
  `revision_stats_language_quality_maj` int(11) NOT NULL DEFAULT '0',
  `revision_stats_style_maj` int(11) NOT NULL DEFAULT '0',
  UNIQUE KEY `primary_id_pass` (`id`,`password`),
  KEY `id_job_to_revise` (`id_job_to_revise`),
  KEY `id_project` (`id_project`) USING BTREE,
  KEY `owner` (`owner`),
  KEY `id_translator` (`id_translator`),
  KEY `first_last_segment_idx` (`job_first_segment`,`job_last_segment`),
  KEY `id` (`id`) USING BTREE,
  KEY `password` (`password`),
  KEY `source` (`source`),
  KEY `target` (`target`),
  KEY `status_owner_idx` (`status_owner`) USING BTREE,
  KEY `status_idx` (`status`) USING BTREE,
  KEY `create_date_idx` (`create_date`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `memory_keys`
--

DROP TABLE IF EXISTS `memory_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `memory_keys` (
  `uid` bigint(20) NOT NULL,
  `key_value` varchar(45) NOT NULL,
  `key_name` varchar(512) DEFAULT NULL,
  `key_tm` tinyint(1) DEFAULT '1',
  `key_glos` tinyint(1) DEFAULT '1',
  `creation_date` timestamp NULL DEFAULT NULL,
  `update_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted` int(11) DEFAULT '0',
  PRIMARY KEY (`uid`,`key_value`),
  KEY `uid_idx` (`uid`) USING BTREE,
  KEY `key_value_idx` (`key_value`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `id_comment` int(11) NOT NULL,
  `id_translator` varchar(100) CHARACTER SET latin1 NOT NULL,
  `status` varchar(45) CHARACTER SET latin1 DEFAULT 'UNREAD',
  PRIMARY KEY (`id`),
  KEY `id_comment` (`id_comment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `original_files_map`
--

DROP TABLE IF EXISTS `original_files_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `original_files_map` (
  `sha1` varchar(100) NOT NULL,
  `source` varchar(50) NOT NULL,
  `target` varchar(50) NOT NULL,
  `deflated_file` longblob,
  `deflated_xliff` longblob,
  `creation_date` date DEFAULT NULL,
  `segmentation_rule` varchar(512) DEFAULT NULL,
  PRIMARY KEY (`sha1`,`source`,`target`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `projects`
--

DROP TABLE IF EXISTS `projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `segment_revisions`
--

DROP TABLE IF EXISTS `segment_revisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `segment_revisions` (
  `id_job` bigint(20) NOT NULL,
  `id_segment` bigint(20) NOT NULL,
  `original_translation` text COMMENT 'The original translation before revisions.',
  `err_typing` varchar(512) NOT NULL,
  `err_translation` varchar(512) NOT NULL,
  `err_terminology` varchar(512) NOT NULL,
  `err_language` varchar(512) NOT NULL,
  `err_style` varchar(512) NOT NULL,
  PRIMARY KEY (`id_job`,`id_segment`),
  KEY `segm_key` (`id_segment`,`id_job`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `segment_translations`
--

DROP TABLE IF EXISTS `segment_translations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `segments`
--

DROP TABLE IF EXISTS `segments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `segments_comments`
--

DROP TABLE IF EXISTS `segments_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `segments_comments` (
  `id` int(11) NOT NULL,
  `id_segment` int(11) NOT NULL,
  `comment` text,
  `create_date` datetime DEFAULT NULL,
  `created_by` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_segment` (`id_segment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `translators`
--

DROP TABLE IF EXISTS `translators`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  UNIQUE KEY `email` (`email`) USING BTREE,
  KEY `api_key` (`api_key`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Current Database: `matecat_analysis`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `matecat_analysis` /*!40100 DEFAULT CHARACTER SET latin1 */;

USE `matecat_analysis`;

--
-- Table structure for table `segment_translations_analysis_queue`
--

DROP TABLE IF EXISTS `segment_translations_analysis_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2015-03-31 20:37:52
CREATE USER 'matecat'@'localhost' IDENTIFIED BY 'matecat01';
GRANT ALL ON matecat.* TO 'matecat'@'localhost' IDENTIFIED BY 'matecat01';
FLUSH PRIVILEGES;
