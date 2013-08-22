-- MySQL dump 10.13  Distrib 5.5.32, for debian-linux-gnu (x86_64)
--
-- Host: 10.30.1.241    Database: matecat
-- ------------------------------------------------------
-- Server version	5.5.31-0+wheezy1-log

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
  `extra_parameters` text,
  `google_api_compliant_version` varchar(45) DEFAULT NULL COMMENT 'credo sia superfluo',
  `penalty` int(11) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `type` (`type`),
  FULLTEXT KEY `name` (`name`),
  FULLTEXT KEY `description` (`description`),
  FULLTEXT KEY `base_url` (`base_url`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `files`
--

DROP TABLE IF EXISTS `files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_project` int(11) NOT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `source_language` varchar(45) NOT NULL,
  `mime_type` varchar(45) DEFAULT NULL,
  `xliff_file` longblob,
  `sha1_original_file` varchar(100) DEFAULT NULL,
  `original_file` longblob,
  PRIMARY KEY (`id`),
  KEY `id_project` (`id_project`),
  KEY `sha1` (`sha1_original_file`) USING HASH
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
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
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `password` varchar(45) DEFAULT NULL,
  `id_project` int(11) NOT NULL,
  `id_translator` varchar(100) NOT NULL DEFAULT 'generic_translator',
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
  `disabled` tinyint(4) NOT NULL,
  `owner` varchar(100) DEFAULT NULL,
  `status_owner` varchar(100) NOT NULL DEFAULT 'active',
  `status_translator` varchar(100) DEFAULT NULL,
  `status` varchar(15) NOT NULL DEFAULT 'active',
  `completed` bit(1) NOT NULL DEFAULT b'0',
  PRIMARY KEY (`id`),
  KEY `id_job_to_revise` (`id_job_to_revise`),
  KEY `id_project` (`id_project`) USING BTREE,
  KEY `owner` (`owner`),
  KEY `id_translator` (`id_translator`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
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
  PRIMARY KEY (`sha1`,`source`,`target`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
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
  KEY `for_debug` (`for_debug`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `segment_translations`
--

DROP TABLE IF EXISTS `segment_translations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `segment_translations` (
  `id_segment` int(11) NOT NULL,
  `id_job` int(11) NOT NULL,
  `status` varchar(45) DEFAULT 'NEW',
  `translation` text,
  `translation_date` datetime DEFAULT NULL,
  `time_to_edit` int(11) DEFAULT '0',
  `match_type` varchar(45) DEFAULT 'NEW',
  `context_hash` blob,
  `eq_word_count` double(20,2) DEFAULT NULL,
  `standard_word_count` double(20,2) DEFAULT NULL,
  `suggestions_array` text,
  `suggestion` text,
  `suggestion_match` int(11) DEFAULT NULL,
  `suggestion_source` varchar(45) DEFAULT NULL,
  `suggestion_position` int(11) DEFAULT NULL,
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
  KEY `warning` (`warning`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

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
  KEY `locked` (`locked`) USING BTREE
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
  `id_file` int(11) NOT NULL,
  `internal_id` varchar(100) DEFAULT NULL,
  `xliff_mrk_id` varchar(70) DEFAULT NULL,
  `xliff_ext_prec_tags` text,
  `xliff_mrk_ext_prec_tags` text,
  `segment` text,
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
  FULLTEXT KEY `segment` (`segment`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
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
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `email` varchar(50) NOT NULL,
  `salt` varchar(50) NOT NULL,
  `pass` varchar(50) NOT NULL,
  `create_date` datetime NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `api_key` varchar(100) NOT NULL,
  PRIMARY KEY (`email`),
  KEY `api_key` (`api_key`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2013-08-21 18:41:27
-- MySQL dump 10.13  Distrib 5.5.32, for debian-linux-gnu (x86_64)
--
-- Host: 10.30.1.241    Database: matecat
-- ------------------------------------------------------
-- Server version	5.5.31-0+wheezy1-log

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
  `extra_parameters` text,
  `google_api_compliant_version` varchar(45) DEFAULT NULL COMMENT 'credo sia superfluo',
  `penalty` int(11) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `type` (`type`),
  FULLTEXT KEY `name` (`name`),
  FULLTEXT KEY `description` (`description`),
  FULLTEXT KEY `base_url` (`base_url`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `engines`
--

LOCK TABLES `engines` WRITE;
/*!40000 ALTER TABLE `engines` DISABLE KEYS */;
INSERT INTO `engines` VALUES (1,'MyMemory (All Pairs)','TM','MyMemory: next generation Translation Memory technology','http://api.mymemory.translated.net','get','set','delete',NULL,'1',0),(2,'FBK-IT (EN->IT)','MT','FBK (EN->IT) Moses Information Technology engine','http://hlt-services2.fbk.eu:8480','translate','update',NULL,NULL,'2',14),(3,'LIUM-IT (EN->DE)','MT','Lium (EN->FR) Moses Information Technology engine','http://193.52.29.52:8001','translate',NULL,NULL,NULL,'2',14),(4,'FBK-LEGAL (EN>IT)','MT','FBK (EN->IT) Moses Legal engine','http://hlt-services2.fbk.eu:8490','translate',NULL,NULL,NULL,'2',14),(5,'LIUM-LEGAL (EN->DE)','MT','Lium (EN->FR) Moses Legal engine','http://193.52.29.52:8002','translate',NULL,NULL,NULL,NULL,14),(6,'TEST PURPOSE FBK (EN->IT)','MT','TEST PURPOSE ONLY  - FBK (EN->IT)','http://hlt-services2.fbk.eu:8482','translate','update',NULL,NULL,'2',14);
/*!40000 ALTER TABLE `engines` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2013-08-21 18:41:27
CREATE USER 'matecat'@'localhost' IDENTIFIED BY 'matecat01';
GRANT ALL ON matecat.* TO 'matecat'@'localhost';
FLUSH PRIVILEGES;
