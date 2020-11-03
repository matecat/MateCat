-- MySQL dump 10.13  Distrib 5.7.32, for Linux (x86_64)
--
-- Host: 127.0.0.1    Database: matecat
-- ------------------------------------------------------
-- Server version	5.5.30

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

/*!40000 DROP DATABASE IF EXISTS `matecat`*/;

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `matecat` /*!40100 DEFAULT CHARACTER SET utf8 */;

USE `matecat`;

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
  UNIQUE KEY `unique_record` (`id_job`,`password`,`job_first_segment`,`job_last_segment`,`is_review`),
  KEY `id_project` (`id_project`) USING BTREE,
  KEY `id_job` (`id_job`) USING BTREE,
  KEY `create_date` (`create_date`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

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
  KEY `id_segment` (`id_job`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

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
-- Table structure for table `dqf_projects_map`
--

DROP TABLE IF EXISTS `dqf_projects_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dqf_projects_map` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_job` bigint(20) NOT NULL,
  `password` varchar(50) NOT NULL,
  `first_segment` int(11) NOT NULL,
  `last_segment` int(11) NOT NULL,
  `dqf_project_id` int(11) NOT NULL,
  `dqf_project_uuid` varchar(255) NOT NULL,
  `dqf_parent_uuid` varchar(255) DEFAULT NULL,
  `archive_date` datetime DEFAULT NULL,
  `create_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `project_type` varchar(50) DEFAULT NULL,
  `uid` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_job` (`id_job`) USING BTREE,
  KEY `first_last_segment` (`first_segment`,`last_segment`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dqf_segments`
--

DROP TABLE IF EXISTS `dqf_segments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dqf_segments` (
  `id_segment` bigint(20) NOT NULL,
  `dqf_segment_id` bigint(20) DEFAULT NULL,
  `dqf_translation_id` bigint(20) DEFAULT NULL,
  UNIQUE KEY `dqf_segment_key` (`id_segment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
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
  PRIMARY KEY (`id`),
  KEY `id_file_idx` (`id_file`),
  KEY `id_project_idx` (`id_project`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

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
  `is_converted` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_project` (`id_project`),
  KEY `sha1` (`sha1_original_file`) USING HASH,
  KEY `filename` (`filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
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
  `dqf_key` varchar(255) DEFAULT NULL,
  `avg_post_editing_effort` float DEFAULT '0',
  `total_raw_wc` bigint(20) DEFAULT '1',
  `standard_analysis_wc` double(20,2) DEFAULT '0.00',
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
  KEY `status_owner_idx` (`status_owner`),
  KEY `status_idx` (`status`),
  KEY `create_date_idx` (`create_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

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
  `email` varchar(45) NOT NULL,
  `delivery_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `job_owner_timezone` decimal(2,1) NOT NULL DEFAULT '0.0',
  `source` varchar(10) NOT NULL,
  `target` varchar(10) NOT NULL,
  PRIMARY KEY (`id_job`,`job_password`),
  KEY `id_translator_idx` (`id_translator_profile`),
  KEY `added_by_idx` (`added_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

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
  KEY `uid_idx` (`uid`),
  KEY `key_value_idx` (`key_value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
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
  `delivery_date` timestamp NOT NULL,
  `currency` varchar(25) NOT NULL DEFAULT 'EUR',
  `price` float(11,2) NOT NULL DEFAULT '0.00',
  `quote_pid` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_job_password_idx` (`id_job`,`password`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

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
-- Table structure for table `phinxlog`
--

DROP TABLE IF EXISTS `phinxlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `phinxlog` (
  `version` bigint(20) NOT NULL,
  `start_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `end_time` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

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
  `value` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_project_and_key` (`id_project`,`key`) USING BTREE,
  KEY `id_project` (`id_project`) USING BTREE
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
  `id_team` int(11) DEFAULT NULL,
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
  `id_assignee` int(10) unsigned DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_customer` (`id_customer`),
  KEY `status_analysis` (`status_analysis`),
  KEY `for_debug` (`instance_id`),
  KEY `remote_ip_address` (`remote_ip_address`),
  KEY `name` (`name`),
  KEY `id_assignee_idx` (`id_assignee`),
  KEY `id_team_idx` (`id_team`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

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
  KEY `id_model` (`id_model`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

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
  `penalty_points` double(20,2) DEFAULT NULL,
  `source_page` int(11) DEFAULT NULL,
  `is_pass` tinyint(4) DEFAULT NULL,
  `force_pass_at` timestamp NULL DEFAULT NULL,
  `reviewed_words_count` int(11) NOT NULL DEFAULT '0',
  `undo_data` text,
  `advancement_wc` float(10,2) NOT NULL DEFAULT '0.00',
  `total_tte` bigint(20) NOT NULL DEFAULT '0',
  `avg_pee` float(10,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `job_pw_source_page` (`id_job`,`password`,`source_page`),
  KEY `id_project` (`id_project`),
  KEY `review_password` (`review_password`),
  KEY `id_job` (`id_job`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

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
  `penalty_points` double(20,2) DEFAULT NULL,
  `comment` text,
  `replies_count` int(11) NOT NULL DEFAULT '0',
  `create_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `rebutted_at` datetime DEFAULT NULL,
  `source_page` int(11) NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `job_and_segment` (`id_job`,`id_segment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

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
  KEY `id_qa_entry` (`id_qa_entry`),
  KEY `create_date` (`create_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

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
-- Table structure for table `replace_events`
--

DROP TABLE IF EXISTS `replace_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `replace_events` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `replace_version` bigint(20) NOT NULL,
  `id_job` bigint(20) NOT NULL,
  `job_password` varchar(45) NOT NULL,
  `id_segment` int(11) NOT NULL,
  `segment_version` int(11) DEFAULT NULL,
  `translation_before_replacement` text,
  `translation_after_replacement` text,
  `source` text,
  `target` text,
  `status` varchar(45) NOT NULL,
  `replacement` text,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `replace_events_current_version`
--

DROP TABLE IF EXISTS `replace_events_current_version`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `replace_events_current_version` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_job` bigint(20) NOT NULL,
  `version` bigint(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

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
-- Table structure for table `segment_original_data`
--

DROP TABLE IF EXISTS `segment_original_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `segment_original_data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_segment` int(11) NOT NULL,
  `map` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
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
  KEY `segm_key` (`id_segment`,`id_job`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

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
  KEY `creation_date` (`creation_date`)
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
  `version_number` int(11) DEFAULT '0',
  `edit_distance` int(11) DEFAULT NULL,
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
  KEY `id_job` (`id_job`),
  KEY `id_segment` (`id_segment`) USING BTREE
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
  KEY `show_in_cat` (`show_in_cattool`) USING BTREE,
  KEY `raw_word_count` (`raw_word_count`) USING BTREE,
  KEY `segment_hash` (`segment_hash`) USING HASH COMMENT 'MD5 hash of segment content'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sequences`
--

DROP TABLE IF EXISTS `sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sequences` (
  `id_segment` bigint(20) unsigned NOT NULL,
  `id_project` bigint(20) unsigned NOT NULL,
  `id_dqf_project` bigint(20) unsigned NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

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
  KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

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
  UNIQUE KEY `uid_src_trg_type_idx` (`uid_translator`,`source`,`target`,`is_revision`),
  KEY `src_idx` (`source`),
  KEY `trg_idx` (`target`),
  KEY `src_trg_idx` (`source`,`target`)
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
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `confirmation_token` (`confirmation_token`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Current Database: `matecat`
--

USE `matecat`;

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
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `show_clients` AS select 1 AS `host_short`,1 AS `users`,1 AS `COUNT(*)` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2020-11-03 19:16:23


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

-- MySQL dump 10.13  Distrib 5.7.32, for Linux (x86_64)
--
-- Host: 127.0.0.1    Database: matecat
-- ------------------------------------------------------
-- Server version	5.5.30

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
-- Dumping data for table `phinxlog`
--

/*!40000 ALTER TABLE `phinxlog` DISABLE KEYS */;
INSERT INTO `phinxlog` VALUES (20150918101657,'2016-12-22 12:24:34','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20150921114813,'2016-12-22 12:24:35','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20150922152051,'2016-12-22 12:24:35','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20151001131124,'2016-12-22 12:24:35','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20151120150352,'2016-12-22 12:24:35','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20151123141623,'2016-12-22 12:24:35','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20151126093945,'2016-12-22 12:24:35','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20151204140144,'2016-12-22 12:24:35','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20151219181543,'2016-12-22 12:24:35','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20151229103454,'2016-12-22 12:24:35','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20160108101432,'2016-12-22 12:24:35','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20160115143225,'2016-12-22 12:24:35','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20160116085841,'2016-12-22 12:24:35','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20160120143540,'2016-12-22 12:24:36','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20160121170252,'2016-12-22 12:24:36','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20160124101801,'2016-12-22 12:24:36','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20160301134214,'2016-12-22 12:24:36','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20160311094715,'2016-12-22 12:24:36','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20160318130527,'2016-12-22 12:24:36','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20160329131606,'2016-12-22 12:24:36','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20160331142550,'2016-12-22 12:24:36','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20160331210238,'2016-12-22 12:24:36','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20160406102209,'2016-12-22 12:24:36','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20160408162842,'2016-12-22 12:24:36','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20160519093951,'2016-12-22 12:24:36','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20160524122147,'2016-12-22 12:24:36','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20160608130816,'2016-12-22 12:24:36','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20160613103347,'2016-12-22 12:24:36','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20160902141754,'2016-12-22 12:24:36','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20160909113520,'2016-12-22 12:24:36','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20160916105911,'2016-12-22 12:24:36','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20161027154703,'2016-12-22 12:24:36','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20161107080000,'2016-12-22 12:24:36','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20161107094229,'2016-12-22 12:24:36','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20161118144241,'2016-12-22 12:24:36','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20161122093431,'2016-12-22 12:24:36','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20161125145959,'2016-12-22 12:24:36','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20161207184244,'2016-12-22 12:24:36','0000-00-00 00:00:00');
INSERT INTO `phinxlog` VALUES (20161219160843,'2017-01-20 12:16:32','2017-01-20 12:16:37');
INSERT INTO `phinxlog` VALUES (20161223190349,'2016-12-23 20:29:57','2016-12-23 20:29:57');
INSERT INTO `phinxlog` VALUES (20161223191009,'2016-12-23 20:54:15','2016-12-23 20:54:15');
INSERT INTO `phinxlog` VALUES (20161223191509,'2016-12-23 21:04:14','2016-12-23 21:04:14');
INSERT INTO `phinxlog` VALUES (20161230151125,'2017-01-20 12:16:37','2017-01-20 12:16:38');
INSERT INTO `phinxlog` VALUES (20170113150724,'2017-02-01 18:50:56','2017-02-01 18:50:56');
INSERT INTO `phinxlog` VALUES (20170201160907,'2017-02-02 10:44:42','2017-02-02 10:44:44');
INSERT INTO `phinxlog` VALUES (20170202125007,'2017-02-15 12:16:07','2017-02-15 12:16:09');
INSERT INTO `phinxlog` VALUES (20170203120331,'2017-02-15 12:16:09','2017-02-15 12:16:09');
INSERT INTO `phinxlog` VALUES (20170203145956,'2017-02-15 12:16:09','2017-02-15 12:16:10');
INSERT INTO `phinxlog` VALUES (20170220193303,'2017-02-22 19:30:58','2017-02-22 19:30:58');
INSERT INTO `phinxlog` VALUES (20170227194426,'2017-02-27 20:46:26','2017-02-27 20:46:27');
INSERT INTO `phinxlog` VALUES (20170303175809,'2017-03-17 17:38:39','2017-03-17 17:38:41');
INSERT INTO `phinxlog` VALUES (20170306121226,'2017-03-13 19:28:56','2017-03-13 19:29:00');
INSERT INTO `phinxlog` VALUES (20170310111529,'2017-03-17 17:56:21','2017-03-17 17:56:21');
INSERT INTO `phinxlog` VALUES (20170313184235,'2017-03-17 17:47:22','2017-03-17 17:47:22');
INSERT INTO `phinxlog` VALUES (20170320191235,'2017-03-23 20:39:11','2017-03-23 20:39:11');
INSERT INTO `phinxlog` VALUES (20170405133041,'2017-04-05 15:37:42','2017-04-05 15:37:42');
INSERT INTO `phinxlog` VALUES (20170410135242,'2017-04-12 13:01:28','2017-04-12 13:01:29');
INSERT INTO `phinxlog` VALUES (20170428150013,'2017-05-03 13:36:24','2017-05-03 13:36:25');
INSERT INTO `phinxlog` VALUES (20170504163201,'2017-05-04 18:38:49','2017-05-04 18:38:50');
INSERT INTO `phinxlog` VALUES (20170511110439,'2017-07-24 11:53:46','2017-07-24 11:53:46');
INSERT INTO `phinxlog` VALUES (20170518102926,'2018-02-06 16:44:29','2018-02-06 16:44:30');
INSERT INTO `phinxlog` VALUES (20170605191514,'2017-09-26 17:09:13','2017-09-26 17:09:13');
INSERT INTO `phinxlog` VALUES (20170711124125,'2017-09-26 19:09:22','2017-09-26 19:09:23');
INSERT INTO `phinxlog` VALUES (20170712141010,'2017-09-26 19:09:23','2017-09-26 19:09:23');
INSERT INTO `phinxlog` VALUES (20170720100436,'2017-09-26 19:09:23','2017-09-26 19:09:24');
INSERT INTO `phinxlog` VALUES (20170829075926,'2017-09-26 19:09:24','2017-09-26 19:09:24');
INSERT INTO `phinxlog` VALUES (20170904190313,'2017-12-18 13:33:08','2017-12-18 13:33:09');
INSERT INTO `phinxlog` VALUES (20170905114929,'2017-12-18 13:33:09','2017-12-18 13:33:09');
INSERT INTO `phinxlog` VALUES (20170926170329,'2017-09-26 19:09:24','2017-09-26 19:09:25');
INSERT INTO `phinxlog` VALUES (20171004154445,'2017-12-18 13:33:09','2017-12-18 13:33:10');
INSERT INTO `phinxlog` VALUES (20171106115121,'2017-12-18 13:33:10','2017-12-18 13:33:12');
INSERT INTO `phinxlog` VALUES (20171129152525,'2017-11-30 12:39:09','2017-11-30 12:39:13');
INSERT INTO `phinxlog` VALUES (20171214110059,'2017-12-18 16:48:13','2017-12-18 16:48:14');
INSERT INTO `phinxlog` VALUES (20180207155126,'2018-03-30 17:15:01','2018-03-30 17:15:03');
INSERT INTO `phinxlog` VALUES (20180330145502,'2018-03-30 17:15:03','2018-03-30 17:15:16');
INSERT INTO `phinxlog` VALUES (20180921144444,'2018-09-21 16:47:50','2018-09-21 16:47:52');
INSERT INTO `phinxlog` VALUES (20180924143503,'2018-09-25 10:47:17','2018-09-25 10:47:20');
INSERT INTO `phinxlog` VALUES (20181026145655,'2018-10-31 12:59:37','2018-10-31 12:59:38');
INSERT INTO `phinxlog` VALUES (20190306105740,'2019-08-16 10:58:30','2019-08-16 10:58:30');
INSERT INTO `phinxlog` VALUES (20190327093656,'2019-09-09 18:16:44','2019-09-09 18:16:45');
INSERT INTO `phinxlog` VALUES (20190517080829,'2019-09-09 18:16:45','2019-09-09 18:16:45');
INSERT INTO `phinxlog` VALUES (20190617082146,'2019-09-09 18:16:45','2019-09-09 18:16:45');
INSERT INTO `phinxlog` VALUES (20190618151911,'2019-09-09 18:16:45','2019-09-09 18:16:45');
INSERT INTO `phinxlog` VALUES (20190627093658,'2019-09-09 18:16:45','2019-09-09 18:16:45');
INSERT INTO `phinxlog` VALUES (20190719095651,'2019-09-09 18:16:45','2019-09-09 18:16:45');
INSERT INTO `phinxlog` VALUES (20190723092053,'2019-09-09 18:16:45','2019-09-09 18:16:45');
INSERT INTO `phinxlog` VALUES (20190812091652,'2019-08-26 17:16:32','2019-08-26 17:16:32');
INSERT INTO `phinxlog` VALUES (20191220115633,'2019-12-20 13:01:16','2019-12-20 13:01:16');
INSERT INTO `phinxlog` VALUES (20200205155333,'2020-02-05 15:54:14','2020-02-05 15:54:14');
INSERT INTO `phinxlog` VALUES (20200317141655,'2020-08-20 10:38:45','2020-08-20 10:38:45');
INSERT INTO `phinxlog` VALUES (20200323145227,'2020-10-07 18:30:09','2020-10-07 18:30:10');
INSERT INTO `phinxlog` VALUES (20200909162350,'2020-09-18 19:09:11','2020-09-18 19:09:11');
INSERT INTO `phinxlog` VALUES (20200921145329,'2020-10-07 18:30:10','2020-10-07 18:30:10');
/*!40000 ALTER TABLE `phinxlog` ENABLE KEYS */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2020-11-03 19:16:23
