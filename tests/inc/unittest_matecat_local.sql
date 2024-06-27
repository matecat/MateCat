-- MySQL dump 10.13  Distrib 5.7.44-48, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: unittest_matecat_local
-- ------------------------------------------------------
-- Server version	5.7.44-48-log

/*!40101 SET NAMES utf8 */;

--
-- Current Database: `unittest_matecat_local`
--

/*!40000 DROP DATABASE IF EXISTS `unittest_matecat_local`*/;

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `unittest_matecat_local` /*!40100 DEFAULT CHARACTER SET utf8 */;

USE `unittest_matecat_local`;

--
-- Table structure for table `activity_log`
--

DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_log` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `id_project` int(10) unsigned DEFAULT NULL,
  `id_job` int(10) unsigned DEFAULT NULL,
  `action` int(10) unsigned NOT NULL,
  `ip` varchar(45) NOT NULL,
  `uid` int(10) unsigned DEFAULT NULL,
  `event_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `memory_key` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`ID`,`event_date`),
  KEY `ip_idx` (`ip`) USING BTREE,
  KEY `id_job_idx` (`id_job`) USING BTREE,
  KEY `id_project_idx` (`id_project`) USING BTREE,
  KEY `uid_idx` (`uid`) USING BTREE,
  KEY `event_date_idx` (`event_date`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_log`
--

/*!40000 ALTER TABLE `activity_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `activity_log` ENABLE KEYS */;

--
-- Table structure for table `api_keys`
--

DROP TABLE IF EXISTS `api_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `api_keys` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` bigint(20) NOT NULL,
  `api_key` varchar(50) NOT NULL,
  `api_secret` varchar(45) NOT NULL,
  `create_date` datetime NOT NULL,
  `last_update` datetime NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `api_key` (`api_key`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `api_keys`
--

/*!40000 ALTER TABLE `api_keys` DISABLE KEYS */;
/*!40000 ALTER TABLE `api_keys` ENABLE KEYS */;

--
-- Table structure for table `blacklist_files`
--

DROP TABLE IF EXISTS `blacklist_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blacklist_files` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_job` bigint(20) NOT NULL,
  `password` varchar(45) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `target` varchar(10) NOT NULL,
  `uid` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_job_password` (`id_job`,`password`),
  KEY `uid` (`uid`) USING BTREE,
  KEY `id_job` (`id_job`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blacklist_files`
--

/*!40000 ALTER TABLE `blacklist_files` DISABLE KEYS */;
/*!40000 ALTER TABLE `blacklist_files` ENABLE KEYS */;

--
-- Table structure for table `chunk_completion_events`
--

DROP TABLE IF EXISTS `chunk_completion_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chunk_completion_events` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_project` bigint(20) NOT NULL,
  `id_job` bigint(20) NOT NULL,
  `uid` bigint(20) DEFAULT NULL,
  `job_first_segment` bigint(20) unsigned NOT NULL,
  `job_last_segment` bigint(20) unsigned NOT NULL,
  `password` varchar(45) NOT NULL,
  `source` varchar(45) NOT NULL,
  `create_date` datetime NOT NULL,
  `remote_ip_address` varchar(45) NOT NULL,
  `is_review` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_project` (`id_project`) USING BTREE,
  KEY `id_job` (`id_job`) USING BTREE,
  KEY `create_date` (`create_date`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chunk_completion_events`
--

/*!40000 ALTER TABLE `chunk_completion_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `chunk_completion_events` ENABLE KEYS */;

--
-- Table structure for table `chunk_completion_updates`
--

DROP TABLE IF EXISTS `chunk_completion_updates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chunk_completion_updates` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_project` bigint(20) NOT NULL,
  `id_job` bigint(20) NOT NULL,
  `uid` bigint(20) DEFAULT NULL,
  `job_first_segment` bigint(20) unsigned NOT NULL,
  `job_last_segment` bigint(20) unsigned NOT NULL,
  `password` varchar(45) NOT NULL,
  `source` varchar(45) NOT NULL,
  `create_date` datetime NOT NULL,
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_translation_at` datetime DEFAULT NULL,
  `is_review` tinyint(1) NOT NULL,
  `remote_ip_address` varchar(45) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_record` (`id_job`,`password`,`job_first_segment`,`job_last_segment`,`is_review`) USING BTREE,
  KEY `id_project` (`id_project`) USING BTREE,
  KEY `id_job` (`id_job`) USING BTREE,
  KEY `create_date` (`create_date`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chunk_completion_updates`
--

/*!40000 ALTER TABLE `chunk_completion_updates` DISABLE KEYS */;
/*!40000 ALTER TABLE `chunk_completion_updates` ENABLE KEYS */;

--
-- Table structure for table `comments`
--

DROP TABLE IF EXISTS `comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comments` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_job` bigint(20) NOT NULL,
  `id_segment` bigint(20) NOT NULL,
  `create_date` datetime NOT NULL,
  `email` varchar(50) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `uid` bigint(20) DEFAULT NULL,
  `resolve_date` datetime DEFAULT NULL,
  `source_page` tinyint(4) DEFAULT NULL,
  `is_owner` tinyint(4) NOT NULL,
  `message_type` tinyint(4) DEFAULT NULL,
  `message` text,
  PRIMARY KEY (`id`),
  KEY `id_job` (`id_job`) USING BTREE,
  KEY `id_segment` (`id_segment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comments`
--

/*!40000 ALTER TABLE `comments` DISABLE KEYS */;
/*!40000 ALTER TABLE `comments` ENABLE KEYS */;

--
-- Table structure for table `connected_services`
--

DROP TABLE IF EXISTS `connected_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `connected_services` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` bigint(20) NOT NULL,
  `service` varchar(30) NOT NULL,
  `remote_id` varchar(255) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `oauth_access_token` text NOT NULL,
  `created_at` timestamp NOT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `expired_at` timestamp NULL DEFAULT NULL,
  `disabled_at` timestamp NULL DEFAULT NULL,
  `is_default` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid_email_service` (`uid`,`email`,`service`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `connected_services`
--

/*!40000 ALTER TABLE `connected_services` DISABLE KEYS */;
/*!40000 ALTER TABLE `connected_services` ENABLE KEYS */;

--
-- Table structure for table `context_groups`
--

DROP TABLE IF EXISTS `context_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `context_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_project` int(11) NOT NULL,
  `id_segment` bigint(20) unsigned DEFAULT NULL,
  `id_file` int(10) unsigned DEFAULT NULL,
  `context_json` varchar(16320) NOT NULL,
  PRIMARY KEY (`id`,`id_project`),
  KEY `id_segment_idx` (`id_segment`) USING BTREE,
  KEY `id_file_idx` (`id_file`) USING BTREE,
  KEY `id_project_idx` (`id_project`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `context_groups`
--

/*!40000 ALTER TABLE `context_groups` DISABLE KEYS */;
/*!40000 ALTER TABLE `context_groups` ENABLE KEYS */;

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
  `stable` tinyint(4) NOT NULL DEFAULT '1',
  `segmentation_rule` varchar(512) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip_converter_UNIQUE` (`ip_converter`),
  UNIQUE KEY `ip_storage_UNIQUE` (`ip_storage`),
  UNIQUE KEY `id_UNIQUE` (`id`) USING BTREE,
  KEY `status_active` (`status_active`),
  KEY `status_offline` (`status_offline`),
  KEY `status_reboot` (`status_reboot`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `converters`
--

/*!40000 ALTER TABLE `converters` DISABLE KEYS */;
/*!40000 ALTER TABLE `converters` ENABLE KEYS */;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `converters_log`
--

/*!40000 ALTER TABLE `converters_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `converters_log` ENABLE KEYS */;

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
  `update_relative_url` varchar(100) DEFAULT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `engines`
--

/*!40000 ALTER TABLE `engines` DISABLE KEYS */;
INSERT INTO engines (id, name, type, description, base_url, translate_relative_url, contribute_relative_url, update_relative_url, delete_relative_url, others, class_load, extra_parameters, google_api_compliant_version, penalty, active, uid) VALUES (10, 'NONE', 'NONE', 'No MT', '', '', null, null, null, '{"gloss_get_relative_url":"glossary/get","gloss_set_relative_url":"glossary/set","gloss_update_relative_url":"glossary/update","glossary_import_relative_url":"glossary/import","glossary_export_relative_url":"glossary/export","gloss_delete_relative_url":"glossary/delete","tmx_import_relative_url":"tmx/import","tmx_status_relative_url":"tmx/status","tmx_export_create_url":"tmx/export/create","tmx_export_check_url":"tmx/export/check","tmx_export_download_url":"tmx/export/download","tmx_export_list_url":"tmx/export/list","tmx_export_email_url":"tmx/export/create","api_key_create_user_url":"createranduser","api_key_check_auth_url":"authkey","analyze_url":"analyze","detect_language_url":"langdetect.php"}', 'NONE', '{}', null, 100, 0, null);
INSERT INTO engines (id, name, type, description, base_url, translate_relative_url, contribute_relative_url, update_relative_url, delete_relative_url, others, class_load, extra_parameters, google_api_compliant_version, penalty, active, uid) VALUES (11, 'ModernMT Lite', 'TM', 'Smart machine translation that learns from your corrections for enhanced quality and productivity thanks to ModernMT’s basic features. To unlock all features, <a href="https://www.modernmt.com/pricing#translators">click here</a>.', 'https://api.mymemory.translated.net', 'get', 'set', 'update', 'delete_by_id', '{"analyze_url":"analyze","api_key_check_auth_url":"authkey","api_key_create_user_url":"createranduser","detect_language_url":"langdetect.php", "glossary_check_relative_url":"v2/glossary/check","glossary_delete_relative_url":"v2/glossary/delete","glossary_domains_relative_url":"v2/glossary/domains","glossary_entry_status_relative_url":"v2/entry/status","glossary_export_relative_url":"v2/glossary/export","glossary_get_relative_url":"v2/glossary/get","glossary_search_relative_url":"v2/glossary/search","glossary_import_relative_url":"v2/glossary/import","glossary_import_status_relative_url":"v2/import/status","glossary_keys_relative_url":"v2/glossary/keys","glossary_set_relative_url":"v2/glossary/set","glossary_update_relative_url":"v2/glossary/update","tmx_export_email_url":"tmx/export/create","tmx_import_relative_url":"tmx/import","tmx_status_relative_url":"v2/import/status","tags_projection":"tags-projection"}', 'MyMemory', '{}', '1', 0, 1, null);
UPDATE engines SET id = 0 WHERE id = 10;
UPDATE engines SET id = 1 WHERE id = 11;
/*!40000 ALTER TABLE `engines` ENABLE KEYS */;

--
-- Table structure for table `file_metadata`
--

DROP TABLE IF EXISTS `file_metadata`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `file_metadata` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_project` bigint(20) NOT NULL,
  `id_file` bigint(20) NOT NULL,
  `key` varchar(255) NOT NULL,
  `value` text NOT NULL,
  `files_parts_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_file_idx` (`id_file`),
  KEY `id_project_idx` (`id_project`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `file_metadata`
--

/*!40000 ALTER TABLE `file_metadata` DISABLE KEYS */;
/*!40000 ALTER TABLE `file_metadata` ENABLE KEYS */;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `file_references`
--

/*!40000 ALTER TABLE `file_references` DISABLE KEYS */;
/*!40000 ALTER TABLE `file_references` ENABLE KEYS */;

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
  `sha1_original_file` varchar(100) DEFAULT NULL,
  `segmentation_rule` varchar(512) DEFAULT NULL,
  `is_converted` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_project` (`id_project`),
  KEY `sha1` (`sha1_original_file`) USING HASH,
  KEY `filename` (`filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `files`
--

/*!40000 ALTER TABLE `files` DISABLE KEYS */;
INSERT INTO `files` VALUES (1886428333,1886428330,'sample.xlf','en-US','xlf','20170609/f7b3c7b3551a1f8e08e079035c7e64e3705f6df1',NULL,NULL);
/*!40000 ALTER TABLE `files` ENABLE KEYS */;

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
-- Dumping data for table `files_job`
--

/*!40000 ALTER TABLE `files_job` DISABLE KEYS */;
INSERT INTO `files_job` VALUES (1886428338,1886428333,NULL,NULL,NULL,NULL,NULL,'NEW');
/*!40000 ALTER TABLE `files_job` ENABLE KEYS */;

--
-- Table structure for table `files_parts`
--

DROP TABLE IF EXISTS `files_parts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `files_parts` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_file` bigint(20) NOT NULL,
  `tag_key` varchar(45) NOT NULL,
  `tag_value` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_file_idx` (`id_file`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `files_parts`
--

/*!40000 ALTER TABLE `files_parts` DISABLE KEYS */;
/*!40000 ALTER TABLE `files_parts` ENABLE KEYS */;

--
-- Table structure for table `job_custom_payable_rates`
--

DROP TABLE IF EXISTS `job_custom_payable_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_custom_payable_rates` (
  `id_job` int(11) NOT NULL,
  `custom_payable_rate_model_id` int(11) NOT NULL,
  `custom_payable_rate_model_name` varchar(255) NOT NULL,
  `custom_payable_rate_model_version` int(11) NOT NULL,
  UNIQUE KEY `id_job_UNIQUE` (`id_job`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_custom_payable_rates`
--

/*!40000 ALTER TABLE `job_custom_payable_rates` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_custom_payable_rates` ENABLE KEYS */;

--
-- Table structure for table `job_metadata`
--

DROP TABLE IF EXISTS `job_metadata`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_metadata` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_job` bigint(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `key` varchar(255) NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_job_password_key` (`id_job`,`password`,`key`) USING BTREE,
  KEY `id_job_password` (`id_job`,`password`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_metadata`
--

/*!40000 ALTER TABLE `job_metadata` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_metadata` ENABLE KEYS */;

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
  `id_translator` varchar(100) DEFAULT '',
  `tm_keys` text NOT NULL,
  `job_type` varchar(45) DEFAULT NULL,
  `source` varchar(45) DEFAULT NULL,
  `target` varchar(45) DEFAULT NULL,
  `total_time_to_edit` bigint(20) DEFAULT '0',
  `avg_post_editing_effort` float DEFAULT '0',
  `only_private_tm` int(11) NOT NULL DEFAULT '0',
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
  `total_raw_wc` bigint(20) DEFAULT '1',
  `standard_analysis_wc` decimal(8,2) unsigned DEFAULT '0.00',
  `approved2_words` float(10,2) NOT NULL DEFAULT '0.00',
  `new_raw_words` float(10,2) NOT NULL DEFAULT '0.00',
  `draft_raw_words` float(10,2) NOT NULL DEFAULT '0.00',
  `translated_raw_words` float(10,2) NOT NULL DEFAULT '0.00',
  `approved_raw_words` float(10,2) NOT NULL DEFAULT '0.00',
  `approved2_raw_words` float(10,2) NOT NULL DEFAULT '0.00',
  `rejected_raw_words` float(10,2) NOT NULL DEFAULT '0.00',
  UNIQUE KEY `primary_id_pass` (`id`,`password`),
  KEY `id_job_to_revise` (`only_private_tm`),
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
INSERT INTO `jobs` VALUES (1886428338,'a90acf203402',1886428330,1,4,NULL,'[{"tm":true,"glos":true,"owner":true,"uid_transl":null,"uid_rev":null,"name":"","key":"XXXXXXXXXXXXXXXX","r":true,"w":true,"r_transl":null,"w_transl":null,"r_rev":null,"w_rev":null,"source":null,"target":null}]',NULL,'en-GB','es-ES',157967,9700,0,34,1,1,'2024-01-01 00:00:00','2024-01-01 00:00:01',0,'foo@example.org',
                           'active',NULL,'active',_binary '\0',0.00,0.00,21751.00,407.00,147.00,'general','{\"NO_MATCH\":100,\"50%-74%\":100,\"75%-84%\":60,\"85%-94%\":60,\"95%-99%\":60,\"100%\":30,\"100%_PUBLIC\":30,\"REPETITIONS\":30,
\"INTERNAL\":60,\"MT\":80}',1,NULL,0.00,0.00,0.00,0.00,0.00,0.00,0.00);
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;

--
-- Table structure for table `jobs_stats`
--

DROP TABLE IF EXISTS `jobs_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs_stats` (
  `id_job` int(11) NOT NULL,
  `password` varchar(45) NOT NULL,
  `fuzzy_band` varchar(20) NOT NULL,
  `source` varchar(45) NOT NULL,
  `target` varchar(45) NOT NULL,
  `total_time_to_edit` bigint(20) NOT NULL DEFAULT '0',
  `avg_post_editing_effort` float DEFAULT NULL,
  `total_raw_wc` bigint(20) DEFAULT '1',
  PRIMARY KEY (`id_job`,`password`,`fuzzy_band`),
  KEY `fuzzybands__index` (`fuzzy_band`),
  KEY `source` (`source`),
  KEY `target` (`target`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs_stats`
--

/*!40000 ALTER TABLE `jobs_stats` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs_stats` ENABLE KEYS */;

--
-- Table structure for table `jobs_translators`
--

DROP TABLE IF EXISTS `jobs_translators`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs_translators` (
  `id_job` int(11) NOT NULL,
  `job_password` varchar(45) NOT NULL,
  `id_translator_profile` int(11) DEFAULT NULL COMMENT 'This value can be NULL because the translator can be anonymous',
  `added_by` int(11) NOT NULL,
  `email` varchar(50) NOT NULL,
  `delivery_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `job_owner_timezone` decimal(2,1) NOT NULL DEFAULT '0.0',
  `source` varchar(10) NOT NULL,
  `target` varchar(10) NOT NULL,
  PRIMARY KEY (`id_job`,`job_password`),
  KEY `id_translator_idx` (`id_translator_profile`) USING BTREE,
  KEY `added_by_idx` (`added_by`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs_translators`
--

/*!40000 ALTER TABLE `jobs_translators` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs_translators` ENABLE KEYS */;

--
-- Table structure for table `language_stats`
--

DROP TABLE IF EXISTS `language_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `language_stats` (
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `source` varchar(255) NOT NULL,
  `target` varchar(255) NOT NULL,
  `fuzzy_band` varchar(20) NOT NULL,
  `total_word_count` float(255,0) DEFAULT NULL,
  `total_post_editing_effort` float(255,0) DEFAULT NULL,
  `total_time_to_edit` float(255,0) DEFAULT NULL,
  `job_count` int(255) DEFAULT NULL,
  PRIMARY KEY (`date`,`source`,`target`,`fuzzy_band`),
  KEY `source_idx` (`source`),
  KEY `fuzzy_idx` (`fuzzy_band`),
  KEY `target_idx` (`target`),
  KEY `date_idx` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `language_stats`
--

/*!40000 ALTER TABLE `language_stats` DISABLE KEYS */;
/*!40000 ALTER TABLE `language_stats` ENABLE KEYS */;

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
  KEY `key_value_idx` (`key_value`) USING BTREE,
  KEY `creation_date` (`creation_date`),
  KEY `update_date` (`update_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `memory_keys`
--

/*!40000 ALTER TABLE `memory_keys` DISABLE KEYS */;
/*!40000 ALTER TABLE `memory_keys` ENABLE KEYS */;

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
-- Dumping data for table `notifications`
--

/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;

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
-- Dumping data for table `original_files_map`
--

/*!40000 ALTER TABLE `original_files_map` DISABLE KEYS */;
/*!40000 ALTER TABLE `original_files_map` ENABLE KEYS */;

--
-- Table structure for table `outsource_confirmation`
--

DROP TABLE IF EXISTS `outsource_confirmation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `outsource_confirmation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_job` int(11) NOT NULL,
  `password` varchar(45) NOT NULL,
  `id_vendor` int(11) NOT NULL DEFAULT '1',
  `vendor_name` varchar(255) NOT NULL DEFAULT 'Translated',
  `create_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `delivery_date` timestamp NOT NULL DEFAULT '2024-01-01 00:00:00',
  `currency` varchar(25) NOT NULL DEFAULT 'EUR',
  `price` float(11,2) NOT NULL DEFAULT '0.00',
  `quote_pid` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_job_password_idx` (`id_job`,`password`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `outsource_confirmation`
--

/*!40000 ALTER TABLE `outsource_confirmation` DISABLE KEYS */;
/*!40000 ALTER TABLE `outsource_confirmation` ENABLE KEYS */;

--
-- Table structure for table `owner_features`
--

DROP TABLE IF EXISTS `owner_features`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `owner_features` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` bigint(20) DEFAULT NULL,
  `id_team` int(11) DEFAULT NULL,
  `feature_code` varchar(45) NOT NULL,
  `options` text,
  `create_date` datetime NOT NULL,
  `last_update` datetime NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid_feature` (`uid`,`feature_code`) USING BTREE,
  UNIQUE KEY `id_team_feature` (`id_team`,`feature_code`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `owner_features`
--

/*!40000 ALTER TABLE `owner_features` DISABLE KEYS */;
/*!40000 ALTER TABLE `owner_features` ENABLE KEYS */;

--
-- Table structure for table `payable_rate_templates`
--

DROP TABLE IF EXISTS `payable_rate_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payable_rate_templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uid` bigint(20) unsigned NOT NULL,
  `version` int(11) unsigned NOT NULL DEFAULT '1',
  `name` varchar(255) NOT NULL,
  `breakdowns` text NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid_name_idx` (`uid`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payable_rate_templates`
--

/*!40000 ALTER TABLE `payable_rate_templates` DISABLE KEYS */;
/*!40000 ALTER TABLE `payable_rate_templates` ENABLE KEYS */;

--
-- Table structure for table `phinxlog`
--

DROP TABLE IF EXISTS `phinxlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `phinxlog` (
  `version` bigint(20) NOT NULL,
  `migration_name` varchar(100) DEFAULT NULL,
  `start_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `end_time` timestamp NOT NULL DEFAULT '2024-01-01 00:00:00',
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `phinxlog`
--

/*!40000 ALTER TABLE `phinxlog` DISABLE KEYS */;
/*!40000 ALTER TABLE `phinxlog` ENABLE KEYS */;

--
-- Table structure for table `project_metadata`
--

DROP TABLE IF EXISTS `project_metadata`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_metadata` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_project` bigint(20) NOT NULL,
  `key` varchar(255) NOT NULL,
  `value` varchar(2048) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_project_and_key` (`id_project`,`key`) USING BTREE,
  KEY `id_project` (`id_project`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_metadata`
--

/*!40000 ALTER TABLE `project_metadata` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_metadata` ENABLE KEYS */;

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
  `instance_id` tinyint(4) DEFAULT NULL,
  `pretranslate_100` int(1) DEFAULT '0',
  `id_qa_model` int(11) DEFAULT NULL,
  `id_team` int(11) DEFAULT NULL,
  `id_assignee` int(10) unsigned DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_customer` (`id_customer`),
  KEY `status_analysis` (`status_analysis`),
  KEY `for_debug` (`instance_id`),
  KEY `remote_ip_address` (`remote_ip_address`),
  KEY `name` (`name`),
  KEY `id_assignee_idx` (`id_assignee`) USING BTREE,
  KEY `id_team_idx` (`id_team`) USING BTREE,
  KEY `create_date_idx` (`create_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `projects`
--

/*!40000 ALTER TABLE `projects` DISABLE KEYS */;
INSERT INTO `projects` VALUES (1886428330,'441ad0a84b52','foo@example.org','testXLif.xlf','2024-01-01 00:00:00',NULL,NULL,'DONE',0.00,0.00,22305.00,'172.17.0.1',0,0,NULL,32786,18052,NULL);
/*!40000 ALTER TABLE `projects` ENABLE KEYS */;

--
-- Table structure for table `qa_archived_reports`
--

DROP TABLE IF EXISTS `qa_archived_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qa_archived_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_by` int(11) NOT NULL,
  `id_project` int(11) NOT NULL,
  `id_job` bigint(20) NOT NULL,
  `password` varchar(45) NOT NULL,
  `job_first_segment` bigint(20) unsigned NOT NULL,
  `job_last_segment` bigint(20) unsigned NOT NULL,
  `create_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `quality_report` text NOT NULL,
  `version` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `id_job_password_idx` (`id_job`,`password`,`job_first_segment`,`job_last_segment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qa_archived_reports`
--

/*!40000 ALTER TABLE `qa_archived_reports` DISABLE KEYS */;
/*!40000 ALTER TABLE `qa_archived_reports` ENABLE KEYS */;

--
-- Table structure for table `qa_categories`
--

DROP TABLE IF EXISTS `qa_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qa_categories` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_model` bigint(20) NOT NULL,
  `label` varchar(255) NOT NULL,
  `id_parent` bigint(20) DEFAULT NULL,
  `severities` text COMMENT 'json field',
  `options` text,
  PRIMARY KEY (`id`),
  KEY `id_model` (`id_model`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qa_categories`
--

/*!40000 ALTER TABLE `qa_categories` DISABLE KEYS */;
INSERT INTO `qa_categories` VALUES (1,1886428326,'Style (readability, consistent style and tone)',NULL,'[{\"label\":\"Enhancement\",\"dqf_id\":1,\"penalty\":0},{\"label\":\"Error\",\"dqf_id\":2,\"penalty\":1},{\"label\":\"Error 2\",\"dqf_id\":3,\"penalty\":3},{\"label\":\"Error 3\",\"dqf_id\":4,\"penalty\":10}]','{\"dqf_id\":4}'),(2,1886428326,'Language quality (grammar, punctuation, spelling)',NULL,'[{\"label\":\"Enhancement\",\"dqf_id\":1,\"penalty\":0},{\"label\":\"Error\",\"dqf_id\":2,\"penalty\":1},{\"label\":\"Error 2\",\"dqf_id\":3,\"penalty\":3},{\"label\":\"Error 3\",\"dqf_id\":4,\"penalty\":10}]','{\"dqf_id\":2}'),(3,1886428326,'Terminology and translation consistency',NULL,'[{\"label\":\"Enhancement\",\"dqf_id\":1,\"penalty\":0},{\"label\":\"Error\",\"dqf_id\":2,\"penalty\":1},{\"label\":\"Error 2\",\"dqf_id\":3,\"penalty\":3},{\"label\":\"Error 3\",\"dqf_id\":4,\"penalty\":10}]','{\"dqf_id\":3}'),(4,1886428326,'Translation errors (mistranslation, additions or omissions)',NULL,'[{\"label\":\"Enhancement\",\"dqf_id\":1,\"penalty\":0},{\"label\":\"Error\",\"dqf_id\":2,\"penalty\":1},{\"label\":\"Error 2\",\"dqf_id\":3,\"penalty\":3},{\"label\":\"Error 3\",\"dqf_id\":4,\"penalty\":10}]','{\"dqf_id\":1}'),(5,1886428326,'Tag issues (mismatches, whitespaces)',NULL,'[{\"label\":\"Enhancement\",\"dqf_id\":1,\"penalty\":0},{\"label\":\"Error\",\"dqf_id\":2,\"penalty\":1},{\"label\":\"Error 2\",\"dqf_id\":3,\"penalty\":3},{\"label\":\"Error 3\",\"dqf_id\":4,\"penalty\":10}]','{\"dqf_id\":5}');
/*!40000 ALTER TABLE `qa_categories` ENABLE KEYS */;

--
-- Table structure for table `qa_chunk_reviews`
--

DROP TABLE IF EXISTS `qa_chunk_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qa_chunk_reviews` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_project` int(11) NOT NULL,
  `id_job` bigint(20) NOT NULL,
  `password` varchar(45) NOT NULL,
  `review_password` varchar(45) NOT NULL,
  `penalty_points` decimal(11,2) DEFAULT NULL,
  `source_page` tinyint(3) unsigned NOT NULL DEFAULT '2',
  `is_pass` tinyint(4) DEFAULT NULL,
  `force_pass_at` timestamp NULL DEFAULT NULL,
  `reviewed_words_count` int(11) NOT NULL DEFAULT '0',
  `undo_data` text,
  `advancement_wc` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_tte` int(10) unsigned NOT NULL DEFAULT '0',
  `avg_pee` decimal(5,2) unsigned NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `job_pw_src_page_idx` (`id_job`,`password`,`source_page`),
  KEY `id_project` (`id_project`) USING BTREE,
  KEY `review_password` (`review_password`) USING BTREE,
  KEY `id_job` (`id_job`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qa_chunk_reviews`
--

/*!40000 ALTER TABLE `qa_chunk_reviews` DISABLE KEYS */;
INSERT INTO `qa_chunk_reviews` VALUES (1,1886428330,1886428338,'a90acf203402','05023cf84288',NULL,1,0,NULL,0,NULL,0.00,0,0.00);
/*!40000 ALTER TABLE `qa_chunk_reviews` ENABLE KEYS */;

--
-- Table structure for table `qa_entries`
--

DROP TABLE IF EXISTS `qa_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qa_entries` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` bigint(20) DEFAULT NULL,
  `id_segment` bigint(20) NOT NULL,
  `id_job` bigint(20) NOT NULL,
  `id_category` bigint(20) NOT NULL,
  `severity` varchar(255) NOT NULL,
  `translation_version` int(11) NOT NULL,
  `start_node` int(11) NOT NULL,
  `start_offset` int(11) NOT NULL,
  `end_node` int(11) NOT NULL,
  `end_offset` int(11) NOT NULL,
  `target_text` varchar(255) DEFAULT NULL,
  `is_full_segment` tinyint(4) NOT NULL,
  `penalty_points` decimal(11,2) DEFAULT NULL,
  `comment` text,
  `replies_count` int(11) NOT NULL DEFAULT '0',
  `create_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `rebutted_at` datetime DEFAULT NULL,
  `source_page` tinyint(3) unsigned NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `job_and_segment` (`id_job`,`id_segment`) USING BTREE,
  KEY `id_segment_idx` (`id_segment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qa_entries`
--

/*!40000 ALTER TABLE `qa_entries` DISABLE KEYS */;
/*!40000 ALTER TABLE `qa_entries` ENABLE KEYS */;

--
-- Table structure for table `qa_entry_comments`
--

DROP TABLE IF EXISTS `qa_entry_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qa_entry_comments` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` bigint(20) DEFAULT NULL,
  `id_qa_entry` bigint(20) NOT NULL,
  `create_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `comment` text,
  `source_page` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_qa_entry` (`id_qa_entry`) USING BTREE,
  KEY `create_date` (`create_date`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qa_entry_comments`
--

/*!40000 ALTER TABLE `qa_entry_comments` DISABLE KEYS */;
/*!40000 ALTER TABLE `qa_entry_comments` ENABLE KEYS */;

--
-- Table structure for table `qa_model_template_categories`
--

DROP TABLE IF EXISTS `qa_model_template_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qa_model_template_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_template` int(11) NOT NULL,
  `id_parent` int(11) DEFAULT NULL,
  `category_label` varchar(255) NOT NULL,
  `code` varchar(45) NOT NULL,
  `sort` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qa_model_template_categories`
--

/*!40000 ALTER TABLE `qa_model_template_categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `qa_model_template_categories` ENABLE KEYS */;

--
-- Table structure for table `qa_model_template_passfail_options`
--

DROP TABLE IF EXISTS `qa_model_template_passfail_options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qa_model_template_passfail_options` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_passfail` int(11) NOT NULL,
  `passfail_label` varchar(45) NOT NULL,
  `passfail_value` varchar(45) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qa_model_template_passfail_options`
--

/*!40000 ALTER TABLE `qa_model_template_passfail_options` DISABLE KEYS */;
/*!40000 ALTER TABLE `qa_model_template_passfail_options` ENABLE KEYS */;

--
-- Table structure for table `qa_model_template_passfails`
--

DROP TABLE IF EXISTS `qa_model_template_passfails`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qa_model_template_passfails` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_template` int(11) NOT NULL,
  `passfail_type` varchar(45) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qa_model_template_passfails`
--

/*!40000 ALTER TABLE `qa_model_template_passfails` DISABLE KEYS */;
/*!40000 ALTER TABLE `qa_model_template_passfails` ENABLE KEYS */;

--
-- Table structure for table `qa_model_template_severities`
--

DROP TABLE IF EXISTS `qa_model_template_severities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qa_model_template_severities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_category` int(11) NOT NULL,
  `severity_code` varchar(45) NOT NULL,
  `severity_label` varchar(45) NOT NULL,
  `penalty` float(11,2) NOT NULL,
  `sort` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qa_model_template_severities`
--

/*!40000 ALTER TABLE `qa_model_template_severities` DISABLE KEYS */;
/*!40000 ALTER TABLE `qa_model_template_severities` ENABLE KEYS */;

--
-- Table structure for table `qa_model_templates`
--

DROP TABLE IF EXISTS `qa_model_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qa_model_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` bigint(20) NOT NULL,
  `version` int(11) NOT NULL,
  `label` varchar(45) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qa_model_templates`
--

/*!40000 ALTER TABLE `qa_model_templates` DISABLE KEYS */;
/*!40000 ALTER TABLE `qa_model_templates` ENABLE KEYS */;

--
-- Table structure for table `qa_models`
--

DROP TABLE IF EXISTS `qa_models`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qa_models` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` bigint(20) DEFAULT NULL,
  `create_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `label` varchar(255) DEFAULT NULL,
  `pass_type` varchar(255) DEFAULT NULL,
  `pass_options` text,
  `hash` int(10) unsigned DEFAULT NULL,
  `qa_model_template_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qa_models`
--

/*!40000 ALTER TABLE `qa_models` DISABLE KEYS */;
INSERT INTO `qa_models` VALUES (1886428326,NULL,'2024-01-01 00:00:00','MateCat default','points_per_thousand','{\"limit\":20}',NULL,NULL);
/*!40000 ALTER TABLE `qa_models` ENABLE KEYS */;

--
-- Table structure for table `remote_files`
--

DROP TABLE IF EXISTS `remote_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `remote_files` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_file` bigint(20) NOT NULL,
  `id_job` bigint(20) NOT NULL,
  `remote_id` varchar(255) NOT NULL,
  `is_original` tinyint(1) DEFAULT '0',
  `connected_service_id` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_file` (`id_file`) USING BTREE,
  KEY `id_job` (`id_job`) USING BTREE,
  KEY `connected_service_id` (`connected_service_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `remote_files`
--

/*!40000 ALTER TABLE `remote_files` DISABLE KEYS */;
/*!40000 ALTER TABLE `remote_files` ENABLE KEYS */;

--
-- Table structure for table `revision_feedbacks`
--

DROP TABLE IF EXISTS `revision_feedbacks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `revision_feedbacks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_job` int(11) NOT NULL,
  `password` varchar(45) NOT NULL,
  `revision_number` int(1) NOT NULL,
  `feedback` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `job_unique_key` (`id_job`,`password`,`revision_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `revision_feedbacks`
--

/*!40000 ALTER TABLE `revision_feedbacks` DISABLE KEYS */;
/*!40000 ALTER TABLE `revision_feedbacks` ENABLE KEYS */;

--
-- Table structure for table `segment_metadata`
--

DROP TABLE IF EXISTS `segment_metadata`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `segment_metadata` (
  `id_segment` bigint(20) NOT NULL,
  `meta_key` varchar(45) NOT NULL,
  `meta_value` varchar(255) NOT NULL,
  KEY `idx_id_segment_meta_key` (`id_segment`,`meta_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `segment_metadata`
--

/*!40000 ALTER TABLE `segment_metadata` DISABLE KEYS */;
/*!40000 ALTER TABLE `segment_metadata` ENABLE KEYS */;

--
-- Table structure for table `segment_notes`
--

DROP TABLE IF EXISTS `segment_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `segment_notes` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_segment` bigint(20) NOT NULL,
  `internal_id` varchar(100) NOT NULL,
  `note` text,
  `json` text,
  PRIMARY KEY (`id`),
  KEY `id_segment` (`id_segment`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `segment_notes`
--

/*!40000 ALTER TABLE `segment_notes` DISABLE KEYS */;
/*!40000 ALTER TABLE `segment_notes` ENABLE KEYS */;

--
-- Table structure for table `segment_original_data`
--

DROP TABLE IF EXISTS `segment_original_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `segment_original_data` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_segment` bigint(20) NOT NULL,
  `map` text,
  PRIMARY KEY (`id`),
  KEY `id_segment_idx` (`id_segment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `segment_original_data`
--

/*!40000 ALTER TABLE `segment_original_data` DISABLE KEYS */;
/*!40000 ALTER TABLE `segment_original_data` ENABLE KEYS */;

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
-- Dumping data for table `segment_revisions`
--

/*!40000 ALTER TABLE `segment_revisions` DISABLE KEYS */;
/*!40000 ALTER TABLE `segment_revisions` ENABLE KEYS */;

--
-- Table structure for table `segment_translation_events`
--

DROP TABLE IF EXISTS `segment_translation_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `segment_translation_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_job` bigint(20) NOT NULL,
  `id_segment` bigint(20) NOT NULL,
  `uid` bigint(20) NOT NULL,
  `version_number` int(11) NOT NULL,
  `source_page` tinyint(4) NOT NULL,
  `status` varchar(45) NOT NULL,
  `create_date` datetime DEFAULT NULL,
  `final_revision` tinyint(4) DEFAULT '0',
  `time_to_edit` int(11) DEFAULT '0',
  PRIMARY KEY (`id`,`id_job`),
  KEY `id_job` (`id_job`) USING BTREE,
  KEY `id_segment` (`id_segment`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `segment_translation_events`
--

/*!40000 ALTER TABLE `segment_translation_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `segment_translation_events` ENABLE KEYS */;

--
-- Table structure for table `segment_translation_versions`
--

DROP TABLE IF EXISTS `segment_translation_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `segment_translation_versions` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_segment` bigint(20) NOT NULL,
  `id_job` bigint(20) NOT NULL,
  `translation` text,
  `version_number` int(11) NOT NULL,
  `creation_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `propagated_from` bigint(20) DEFAULT NULL,
  `time_to_edit` int(11) DEFAULT NULL,
  `raw_diff` text,
  `old_status` int(11) DEFAULT NULL,
  `new_status` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_segment` (`id_segment`) USING BTREE,
  KEY `id_job` (`id_job`) USING BTREE,
  KEY `creation_date` (`creation_date`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `segment_translation_versions`
--

/*!40000 ALTER TABLE `segment_translation_versions` DISABLE KEYS */;
INSERT INTO `segment_translation_versions` VALUES (1886428367,1,1886428338,'Mondo, ciao',0,'2017-07-12 15:00:00',NULL,2345,NULL,NULL,NULL),(1886428369,1,1886428338,'Mondo  ciao',1,'2017-07-12 15:00:10',NULL,2345,NULL,NULL,NULL),(1886428370,1,1886428338,'Ciao... mondo',2,'2017-07-12 15:00:20',NULL,2345,NULL,NULL,NULL);
/*!40000 ALTER TABLE `segment_translation_versions` ENABLE KEYS */;

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
  `version_number` int(11) DEFAULT '0',
  `edit_distance` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_segment`,`id_job`),
  KEY `id_job` (`id_job`),
  KEY `translation_date` (`translation_date`) USING BTREE,
  KEY `segment_hash` (`segment_hash`) USING HASH
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `segment_translations`
--

/*!40000 ALTER TABLE `segment_translations` DISABLE KEYS */;
INSERT INTO `segment_translations` VALUES (1,1886428338,'f88037cf8f5d27f475cee79c603cecae',NULL,'TRANSLATED','Ciao Ciao mondo 4WD &amp; ampolla %{variable}%',NULL,0,'NO_MATCH',NULL,3.00,3.00,NULL,NULL,NULL,NULL,NULL,0.00000000000000,'UNDONE',0,0,NULL,0,NULL),(2,1886428338,'3e25960a79dbc69b674cd4ec67a72c62',NULL,'TRANSLATED','Ciao mondo &#13;&#13;',NULL,0,'95%-99%',NULL,1.20,1.20,NULL,NULL,NULL,NULL,NULL,0.00000000000000,'UNDONE',0,0,NULL,0,NULL),(3,1886428338,'37d2a389ba71f6de1df030220703785d',NULL,'TRANSLATED','Anche questa unità ha un &quot;commento&quot;;',NULL,0,'NO_MATCH',NULL,6.00,6.00,NULL,NULL,NULL,NULL,NULL,0.00000000000000,'UNDONE',0,0,NULL,0,NULL),(4,1886428338,'3e25960a79dbc69b674cd4ec67a72c62',NULL,'TRANSLATED','Ciao mondo',NULL,0,'REPETITIONS',NULL,0.60,0.60,NULL,NULL,NULL,NULL,NULL,0.00000000000000,'UNDONE',0,0,NULL,0,NULL);
/*!40000 ALTER TABLE `segment_translations` ENABLE KEYS */;

--
-- Table structure for table `segment_translations_splits`
--

DROP TABLE IF EXISTS `segment_translations_splits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `segment_translations_splits` (
  `id_segment` bigint(20) NOT NULL,
  `id_job` bigint(20) NOT NULL,
  `source_chunk_lengths` varchar(1024) NOT NULL DEFAULT '[]',
  `target_chunk_lengths` varchar(1024) NOT NULL DEFAULT '{"len":[0],"statuses":["DRAFT"]}',
  PRIMARY KEY (`id_segment`,`id_job`),
  KEY `id_job` (`id_job`) USING BTREE,
  KEY `id_segment` (`id_segment`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `segment_translations_splits`
--

/*!40000 ALTER TABLE `segment_translations_splits` DISABLE KEYS */;
/*!40000 ALTER TABLE `segment_translations_splits` ENABLE KEYS */;

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
  KEY `show_in_cat` (`show_in_cattool`) USING BTREE,
  KEY `raw_word_count` (`raw_word_count`) USING BTREE,
  KEY `segment_hash` (`segment_hash`) USING HASH COMMENT 'MD5 hash of segment content'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `segments`
--

/*!40000 ALTER TABLE `segments` DISABLE KEYS */;
INSERT INTO `segments` VALUES (1,1886428333,NULL,'251971551069',NULL,NULL,NULL,'Hello Hello world 4WD &amp; ampoule %{variable}%','f88037cf8f5d27f475cee79c603cecae',NULL,NULL,3.00,1),(2,1886428333,NULL,'251971551065',NULL,NULL,NULL,'Hello world &#13;&#13;','3e25960a79dbc69b674cd4ec67a72c62',NULL,NULL,2.00,1),(3,1886428333,NULL,'251971551066',NULL,NULL,NULL,'This unit has a &quot;comment&quot; too;','37d2a389ba71f6de1df030220703785d',NULL,NULL,6.00,1),(4,1886428333,NULL,'251971551067',NULL,NULL,NULL,'Hello world qarkullimit” &amp; faturës.','3e25960a79dbc69b674cd4ec67a72c62',NULL,NULL,2.00,1);
/*!40000 ALTER TABLE `segments` ENABLE KEYS */;

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
-- Dumping data for table `segments_comments`
--

/*!40000 ALTER TABLE `segments_comments` DISABLE KEYS */;
/*!40000 ALTER TABLE `segments_comments` ENABLE KEYS */;

--
-- Table structure for table `sequences`
--

DROP TABLE IF EXISTS `sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sequences` (
  `id_segment` bigint(20) unsigned NOT NULL,
  `id_project` bigint(20) unsigned NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sequences`
--

/*!40000 ALTER TABLE `sequences` DISABLE KEYS */;
INSERT INTO `sequences` VALUES (1,1);
/*!40000 ALTER TABLE `sequences` ENABLE KEYS */;

--
-- Temporary table structure for view `show_clients`
--

DROP TABLE IF EXISTS `show_clients`;
/*!50001 DROP VIEW IF EXISTS `show_clients`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `show_clients` AS SELECT 
 1 AS `host_short`,
 1 AS `users`,
 1 AS `COUNT(*)`*/;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `teams`
--

DROP TABLE IF EXISTS `teams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_by` bigint(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `type` varchar(45) NOT NULL DEFAULT 'personal',
  PRIMARY KEY (`id`),
  KEY `created_by_idx` (`created_by`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teams`
--

/*!40000 ALTER TABLE `teams` DISABLE KEYS */;
/*!40000 ALTER TABLE `teams` ENABLE KEYS */;

--
-- Table structure for table `teams_users`
--

DROP TABLE IF EXISTS `teams_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teams_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_team` int(11) DEFAULT NULL,
  `uid` bigint(20) NOT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_team_uid` (`id_team`,`uid`) USING BTREE,
  KEY `uid` (`uid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teams_users`
--

/*!40000 ALTER TABLE `teams_users` DISABLE KEYS */;
/*!40000 ALTER TABLE `teams_users` ENABLE KEYS */;

--
-- Table structure for table `translation_warnings`
--

DROP TABLE IF EXISTS `translation_warnings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `translation_warnings` (
  `id_job` bigint(20) NOT NULL,
  `id_segment` bigint(20) NOT NULL,
  `severity` tinyint(4) NOT NULL,
  `scope` varchar(255) NOT NULL,
  `data` text NOT NULL,
  KEY `id_job` (`id_job`) USING BTREE,
  KEY `id_segment` (`id_segment`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `translation_warnings`
--

/*!40000 ALTER TABLE `translation_warnings` DISABLE KEYS */;
/*!40000 ALTER TABLE `translation_warnings` ENABLE KEYS */;

--
-- Table structure for table `translator_profiles`
--

DROP TABLE IF EXISTS `translator_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `translator_profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid_translator` int(11) NOT NULL,
  `is_revision` tinyint(1) NOT NULL DEFAULT '0',
  `translated_words` float(11,2) NOT NULL DEFAULT '0.00',
  `revised_words` float(11,2) NOT NULL DEFAULT '0.00',
  `source` varchar(10) NOT NULL,
  `target` varchar(10) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid_src_trg_type_idx` (`uid_translator`,`source`,`target`,`is_revision`) USING BTREE,
  KEY `src_idx` (`source`) USING BTREE,
  KEY `trg_idx` (`target`) USING BTREE,
  KEY `src_trg_idx` (`source`,`target`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `translator_profiles`
--

/*!40000 ALTER TABLE `translator_profiles` DISABLE KEYS */;
/*!40000 ALTER TABLE `translator_profiles` ENABLE KEYS */;

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
-- Dumping data for table `translators`
--

/*!40000 ALTER TABLE `translators` DISABLE KEYS */;
/*!40000 ALTER TABLE `translators` ENABLE KEYS */;

--
-- Table structure for table `user_metadata`
--

DROP TABLE IF EXISTS `user_metadata`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_metadata` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` bigint(20) NOT NULL,
  `key` varchar(255) NOT NULL,
  `value` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid_and_key` (`uid`,`key`) USING BTREE,
  KEY `uid` (`uid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_metadata`
--

/*!40000 ALTER TABLE `user_metadata` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_metadata` ENABLE KEYS */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `uid` bigint(20) NOT NULL AUTO_INCREMENT,
  `email` varchar(50) NOT NULL,
  `salt` varchar(50) DEFAULT NULL,
  `pass` varchar(255) DEFAULT NULL,
  `create_date` datetime NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `oauth_access_token` text,
  `email_confirmed_at` timestamp NULL DEFAULT NULL,
  `new_pass` varchar(50) DEFAULT NULL,
  `confirmation_token` varchar(50) DEFAULT NULL,
  `confirmation_token_created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`uid`),
  UNIQUE KEY `email` (`email`) USING BTREE,
  UNIQUE KEY `confirmation_token` (`confirmation_token`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1886428310,'fabrizio@translated.net','foobar','1234abcd','2024-01-01 00:00:00','Fabrizio','Regini',NULL,NULL,NULL,NULL,'2024-01-01 00:00:00');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;

--
-- Current Database: `unittest_matecat_local`
--

USE `unittest_matecat_local`;

--
-- Final view structure for view `show_clients`
--

/*!50001 DROP VIEW IF EXISTS `show_clients`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8 */;
/*!50001 SET character_set_results     = utf8 */;
/*!50001 SET collation_connection      = utf8_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`admin`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `show_clients` AS select 1 AS `host_short`,1 AS `users`,1 AS `COUNT(*)` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

-- Dump completed on 2024-05-16 17:12:56
