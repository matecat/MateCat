-- MySQL dump 10.13  Distrib 5.5.32, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: unittest_matecat_local
-- ------------------------------------------------------
-- Server version	5.5.32-0ubuntu0.13.04.1

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
-- Current Database: `unittest_matecat_local`
--
DROP SCHEMA IF EXISTS `unittest_matecat_local`;

CREATE SCHEMA `unittest_matecat_local` /*!40100 DEFAULT CHARACTER SET utf8 */;

USE `unittest_matecat_local`;

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
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `engines`
--

LOCK TABLES `engines` WRITE;
/*!40000 ALTER TABLE `engines` DISABLE KEYS */;
INSERT INTO `engines` VALUES
(0, 'NONE', 'NONE', 'No MT', '', '', NULL, NULL, NULL, NULL, 100),
(1, 'MyMemory (All Pairs)', 'TM', 'MyMemory: next generation Translation Memory technology', 'http://api.mymemory.translated.net', 'get', 'set', 'delete', NULL, '1', 0),
(2, 'FBK-IT (EN->IT)', 'MT', 'FBK (EN->IT) Moses Information Technology engine', 'http://hlt-services2.fbk.eu:8601', 'translate', 'update', NULL, NULL, '2', 14),
(3, 'LIUM-IT (EN->DE)', 'MT', 'Lium (EN->FR) Moses Information Technology engine', 'http://193.52.29.52:8001', 'translate', NULL, NULL, NULL, '2', 14),
(4, 'FBK-LEGAL (EN>IT)', 'MT', 'FBK (EN->IT) Moses Legal engine', 'http://hlt-services2.fbk.eu:8701', 'translate', NULL, NULL, NULL, '2', 14),
(5, 'LIUM-LEGAL (EN->DE)', 'MT', 'Lium (EN->FR) Moses Legal engine', 'http://193.52.29.52:8002', 'translate', NULL, NULL, NULL, NULL, 14),
(6, 'TEST PURPOSE FBK (EN->IT)', 'MT', 'TEST PURPOSE ONLY  - FBK (EN->IT)', 'http://hlt-services2.fbk.eu:8482', 'translate', 'update', NULL, NULL, '2', 14),
(7, 'FBK-LEGAL Online (EN->IT)', 'MT', 'FBK (EN->IT) Online learning. Moses Legal engine.', 'http://hlt-services2.fbk.eu:8702', 'translate', 'update', NULL, NULL, '2', 14);
/*!40000 ALTER TABLE `engines` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=MyISAM AUTO_INCREMENT=5301 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `files`
--

LOCK TABLES `files` WRITE;
/*!40000 ALTER TABLE `files` DISABLE KEYS */;
INSERT INTO `files` VALUES (5300,4719,'WhiteHouse (1).doc','en-US','doc','<?xml version=\"1.0\" encoding=\"utf-8\"?><xliff xmlns:sdl=\"http://sdl.com/FileTypes/SdlXliff/1.0\" version=\"1.2\" sdl:version=\"1.0\" xmlns=\"urn:oasis:names:tc:xliff:document:1.2\"><file original=\"\\\\10.11.0.11\\trados\\automation\\projects\\efbec960-08f9-4702-b574-a0f530a2e4d3\\proj\\en-US\\WhiteHouse.doc\" source-language=\"en-US\" datatype=\"x-sdlfilterframework2\" target-language=\"it-IT\"><header><reference><internal-file form=\"base64\">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\r\n</internal-file></reference><reference><internal-file form=\"base64\">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\r\nAAA=\r\n</internal-file></reference><sdl:ref-files><sdl:ref-file uid=\"0\" id=\"http://www.sdl.com/Sdl.FileTypeSupport.Framework.Adapter.Framework1/Framework1FilterDefinition\" name=\"ao3rjecy.dwf.tmp\" descr=\"Filter Framework 1 filter definition file with settings used at the time of extraction.\" expected-use=\"Extraction, Generation\" pref-reftype=\"Embed\" /><sdl:ref-file uid=\"1\" id=\"http://www.sdl.com/Sdl.FileTypeSupport.Framework.Adapter.Framework1/OriginalFile\" name=\"lg4ugksx.wq0.doc\" o-path=\"\\\\10.11.0.11\\trados\\automation\\projects\\efbec960-08f9-4702-b574-a0f530a2e4d3\\proj\\en-US\\WhiteHouse.doc\" date=\"10/09/2013 14:02:31\" descr=\"Original file before processed by the Filter Framework 1 adapter.\" expected-use=\"Generation\" /></sdl:ref-files><file-info xmlns=\"http://sdl.com/FileTypes/SdlXliff/1.0\"><value key=\"SDL:FileId\">17ec0af2-7aa9-4344-8f34-4587e76ad65f</value><value key=\"SDL:CreationDate\">10/09/2013 16:03:10</value><value key=\"SDL:OriginalFilePath\">\\\\10.11.0.11\\trados\\automation\\projects\\efbec960-08f9-4702-b574-a0f530a2e4d3\\proj\\en-US\\WhiteHouse.doc</value><value key=\"SDL:AutoClonedFlagSupported\">True</value><value key=\"SDL:FF1PluginInfo\">&lt;DisplayComplexScriptsAndAsianTextFontSettings&gt;Off&lt;/DisplayComplexScriptsAndAsianTextFontSettings&gt;&lt;TextBoxesOrder&gt;TopLeftBottomRightByRow&lt;/TextBoxesOrder&gt;&lt;FootnoteEndnoteCustomMarkRepresentation&gt;Tag&lt;/FootnoteEndnoteCustomMarkRepresentation&gt;&lt;NonAcceptedOrRejectedChangesHandling&gt;Ignore&lt;/NonAcceptedOrRejectedChangesHandling&gt;&lt;CommentProcessing&gt;On&lt;/CommentProcessing&gt;&lt;HyperlinkProcessing&gt;Off&lt;/HyperlinkProcessing&gt;&lt;WordDocPropertyContentsDisplay&gt;Off&lt;/WordDocPropertyContentsDisplay&gt;&lt;BreakTagType&gt;Internal&lt;/BreakTagType&gt;&lt;DisplayFormatting&gt;On&lt;/DisplayFormatting&gt;&lt;DisplayFontMapping&gt;On&lt;/DisplayFontMapping&gt;&lt;FontMappingRules&gt;&lt;/FontMappingRules&gt;&lt;ColourAdaptation&gt;DarkenedLightColours&lt;/ColourAdaptation&gt;&lt;FontAdaptation&gt;ResizeToVisible&lt;/FontAdaptation&gt;&lt;MinFontSize&gt;10&lt;/MinFontSize&gt;&lt;ReductionTreshold&gt;20&lt;/ReductionTreshold&gt;&lt;ReductionFactor&gt;10&lt;/ReductionFactor&gt;&lt;SingleSize&gt;10&lt;/SingleSize&gt;</value><value key=\"ParagraphTextDirections\"></value><sniff-info><detected-source-lang detection-level=\"Guess\" lang=\"en-US\" /></sniff-info></file-info><sdl:filetype-info><sdl:filetype-id>Word 2000-2003 v 1.0.0.0</sdl:filetype-id></sdl:filetype-info><fmt-defs xmlns=\"http://sdl.com/FileTypes/SdlXliff/1.0\"><fmt-def id=\"1\"><value key=\"FontName\">Times New Roman</value><value key=\"FontSize\">12</value><value key=\"TextColor\">Black</value></fmt-def><fmt-def id=\"2\"><value key=\"FontName\">sans-serif</value><value key=\"Bold\">True</value><value key=\"Italic\">False</value><value key=\"FontSize\">10</value><value key=\"smallcaps\">off</value><value key=\"allcaps\">off</value><value key=\"spacing\">0.0</value></fmt-def><fmt-def id=\"3\"><value key=\"FontName\">sans-serif</value><value key=\"Bold\">False</value><value key=\"Italic\">False</value><value key=\"FontSize\">10</value><value key=\"smallcaps\">off</value><value key=\"allcaps\">off</value><value key=\"spacing\">0.0</value></fmt-def><fmt-def id=\"4\"><value key=\"style\">Corpo del testo</value><value key=\"FontName\">Times New Roman</value><value key=\"FontSize\">12</value><value key=\"TextColor\">Black</value></fmt-def></fmt-defs><cxt-defs xmlns=\"http://sdl.com/FileTypes/SdlXliff/1.0\"><cxt-def id=\"1\" type=\"sdl:paragraph\"><fmt id=\"1\" /><props><value key=\"generic\">Corpo del testo</value></props></cxt-def></cxt-defs><tag-defs xmlns=\"http://sdl.com/FileTypes/SdlXliff/1.0\"><tag id=\"pt2\"><bpt name=\"cf\" word-end=\"false\">&lt;cf font=\"sans-serif\" bold=\"on\" italic=\"off\" size=\"10\" smallcaps=\"off\" allcaps=\"off\" spacing=\"0.0\"&gt;</bpt><ept name=\"cf\" word-end=\"false\">&lt;/cf&gt;</ept><fmt id=\"2\" /></tag><tag id=\"pt3\"><bpt name=\"cf\" word-end=\"false\">&lt;cf font=\"sans-serif\" bold=\"off\" italic=\"off\" size=\"10\" smallcaps=\"off\" allcaps=\"off\" spacing=\"0.0\"&gt;</bpt><ept name=\"cf\" word-end=\"false\">&lt;/cf&gt;</ept><fmt id=\"3\" /></tag><tag id=\"pt1\"><bpt name=\"csf\" word-end=\"false\">&lt;csf style=\"Corpo del testo\" font=\"Times New Roman\" size=\"12\" fontcolour=\"0x0\"&gt;</bpt><ept name=\"csf\" word-end=\"false\">&lt;/csf&gt;</ept><fmt id=\"4\" /></tag><tag id=\"pt5\"><bpt name=\"cf\" word-end=\"false\">&lt;cf font=\"sans-serif\" bold=\"off\" italic=\"off\" size=\"10\" smallcaps=\"off\" allcaps=\"off\" spacing=\"0.0\"&gt;</bpt><ept name=\"cf\" word-end=\"false\">&lt;/cf&gt;</ept><fmt id=\"3\" /></tag><tag id=\"pt4\"><bpt name=\"csf\" word-end=\"false\">&lt;csf style=\"Corpo del testo\" font=\"Times New Roman\" size=\"12\" fontcolour=\"0x0\"&gt;</bpt><ept name=\"csf\" word-end=\"false\">&lt;/csf&gt;</ept><fmt id=\"4\" /></tag><tag id=\"pt7\"><bpt name=\"cf\" word-end=\"false\">&lt;cf font=\"sans-serif\" bold=\"off\" italic=\"off\" size=\"10\" smallcaps=\"off\" allcaps=\"off\" spacing=\"0.0\"&gt;</bpt><ept name=\"cf\" word-end=\"false\">&lt;/cf&gt;</ept><fmt id=\"3\" /></tag><tag id=\"pt6\"><bpt name=\"csf\" word-end=\"false\">&lt;csf style=\"Corpo del testo\" font=\"Times New Roman\" size=\"12\" fontcolour=\"0x0\"&gt;</bpt><ept name=\"csf\" word-end=\"false\">&lt;/csf&gt;</ept><fmt id=\"4\" /></tag><tag id=\"pt9\"><bpt name=\"cf\" word-end=\"false\">&lt;cf font=\"sans-serif\" bold=\"off\" italic=\"off\" size=\"10\" smallcaps=\"off\" allcaps=\"off\" spacing=\"0.0\"&gt;</bpt><ept name=\"cf\" word-end=\"false\">&lt;/cf&gt;</ept><fmt id=\"3\" /></tag><tag id=\"pt8\"><bpt name=\"csf\" word-end=\"false\">&lt;csf style=\"Corpo del testo\" font=\"Times New Roman\" size=\"12\" fontcolour=\"0x0\"&gt;</bpt><ept name=\"csf\" word-end=\"false\">&lt;/csf&gt;</ept><fmt id=\"4\" /></tag><tag id=\"pt11\"><bpt name=\"cf\" word-end=\"false\">&lt;cf font=\"sans-serif\" bold=\"off\" italic=\"off\" size=\"10\" smallcaps=\"off\" allcaps=\"off\" spacing=\"0.0\"&gt;</bpt><ept name=\"cf\" word-end=\"false\">&lt;/cf&gt;</ept><fmt id=\"3\" /></tag><tag id=\"pt10\"><bpt name=\"csf\" word-end=\"false\">&lt;csf style=\"Corpo del testo\" font=\"Times New Roman\" size=\"12\" fontcolour=\"0x0\"&gt;</bpt><ept name=\"csf\" word-end=\"false\">&lt;/csf&gt;</ept><fmt id=\"4\" /></tag><tag id=\"1\"><st name=\"paragraph\">&lt;paragraph style=\"Corpo del testo\" font=\"Times New Roman\" size=\"12\" fontcolour=\"0x0\"&gt;</st><props><value key=\"EndEdge\">Angle</value></props></tag><tag id=\"8\"><st name=\"paragraph\">&lt;/paragraph&gt;</st><props><value key=\"StartEdge\">Angle</value></props></tag><tag id=\"9\"><st name=\"paragraph\">&lt;paragraph style=\"Corpo del testo\" font=\"Times New Roman\" size=\"12\" fontcolour=\"0x0\"&gt;</st><props><value key=\"EndEdge\">Angle</value></props></tag><tag id=\"14\"><st name=\"paragraph\">&lt;/paragraph&gt;</st><props><value key=\"StartEdge\">Angle</value></props></tag><tag id=\"15\"><st name=\"paragraph\">&lt;paragraph style=\"Corpo del testo\" font=\"Times New Roman\" size=\"12\" fontcolour=\"0x0\"&gt;</st><props><value key=\"EndEdge\">Angle</value></props></tag><tag id=\"20\"><st name=\"paragraph\">&lt;/paragraph&gt;</st><props><value key=\"StartEdge\">Angle</value></props></tag><tag id=\"21\"><st name=\"paragraph\">&lt;paragraph style=\"Corpo del testo\" font=\"Times New Roman\" size=\"12\" fontcolour=\"0x0\"&gt;</st><props><value key=\"EndEdge\">Angle</value></props></tag><tag id=\"26\"><st name=\"paragraph\">&lt;/paragraph&gt;</st><props><value key=\"StartEdge\">Angle</value></props></tag><tag id=\"27\"><st name=\"paragraph\">&lt;paragraph style=\"Corpo del testo\" font=\"Times New Roman\" size=\"12\" fontcolour=\"0x0\"&gt;</st><props><value key=\"EndEdge\">Angle</value></props></tag><tag id=\"32\"><st name=\"paragraph\">&lt;/paragraph&gt;</st><props><value key=\"StartEdge\">Angle</value></props></tag><tag id=\"33\"><st name=\"paragraph\">&lt;paragraph style=\"Stile predefinito\" font=\"Times New Roman\" size=\"12\" fontcolour=\"0x0\"&gt;</st><props><value key=\"EndEdge\">Angle</value></props></tag><tag id=\"34\"><st name=\"paragraph\">&lt;/paragraph&gt;</st><props><value key=\"StartEdge\">Angle</value></props></tag><tag id=\"35\"><st name=\"paragraph\">&lt;paragraph style=\"Stile predefinito\" font=\"Times New Roman\" size=\"12\" fontcolour=\"0x0\"&gt;</st><props><value key=\"EndEdge\">Angle</value></props></tag><tag id=\"36\"><st name=\"paragraph\">&lt;/paragraph&gt;</st><props><value key=\"StartEdge\">Angle</value></props></tag></tag-defs></header><body>\r\n<trans-unit id=\"92598d27-407e-4e68-90c7-b1c4b9b03d02\" translate=\"no\"><source><x id=\"1\" />\r\n</source></trans-unit><group><sdl:cxts><sdl:cxt id=\"1\" /></sdl:cxts>\r\n<trans-unit id=\"4df8461a-b0bc-4003-b411-ab5c0cc7ff0e\"><source><g id=\"pt1\"><g id=\"pt2\">WASHINGTON </g><g id=\"pt3\">— The Treasury Department and Internal Revenue Service today requested public comment on issues relating to the shared responsibility provisions included in the Affordable Care Act that will apply to certain employers starting in 2014.</g></g></source><seg-source><g id=\"pt1\"><mrk mtype=\"seg\" mid=\"1\"><g id=\"pt2\">WASHINGTON </g><g id=\"pt3\">— The Treasury Department and Internal Revenue Service today requested public comment on issues relating to the shared responsibility provisions included in the Affordable Care Act that will apply to certain employers starting in 2014.</g></mrk></g></seg-source><target><g id=\"pt1\"><mrk mtype=\"seg\" mid=\"1\"><g id=\"pt2\">WASHINGTON </g><g id=\"pt3\">— The Treasury Department and Internal Revenue Service today requested public comment on issues relating to the shared responsibility provisions included in the Affordable Care Act that will apply to certain employers starting in 2014.</g></mrk></g></target><sdl:seg-defs><sdl:seg id=\"1\" origin=\"source\" /></sdl:seg-defs></trans-unit>\r\n<trans-unit id=\"feb22abd-a22f-4c1b-82ab-216ff829fbba\" translate=\"no\"><source>\r\n<x id=\"8\" />\r\n<x id=\"9\" />\r\n</source></trans-unit></group><group><sdl:cxts><sdl:cxt id=\"1\" /></sdl:cxts>\r\n<trans-unit id=\"3127871f-936d-4c20-ae94-de295a60e7f2\"><source><g id=\"pt4\"><g id=\"pt5\">Under the Affordable Care Act, employers with 50 or more full-time employees that do not offer affordable health coverage to their full-time employees may be required to make a shared responsibility payment.  The law specifically exempts small firms that have fewer than 50 full-time employees. This provision takes effect in 2014.</g></g></source><seg-source><g id=\"pt4\"><g id=\"pt5\"><mrk mtype=\"seg\" mid=\"2\">Under the Affordable Care Act, employers with 50 or more full-time employees that do not offer affordable health coverage to their full-time employees may be required to make a shared responsibility payment.</mrk>  <mrk mtype=\"seg\" mid=\"3\">The law specifically exempts small firms that have fewer than 50 full-time employees.</mrk> <mrk mtype=\"seg\" mid=\"4\">This provision takes effect in 2014.</mrk></g></g></seg-source><target><g id=\"pt4\"><g id=\"pt5\"><mrk mtype=\"seg\" mid=\"2\">Under the Affordable Care Act, employers with 50 or more full-time employees that do not offer affordable health coverage to their full-time employees may be required to make a shared responsibility payment.</mrk>  <mrk mtype=\"seg\" mid=\"3\">The law specifically exempts small firms that have fewer than 50 full-time employees.</mrk> <mrk mtype=\"seg\" mid=\"4\">This provision takes effect in 2014.</mrk></g></g></target><sdl:seg-defs><sdl:seg id=\"2\" origin=\"source\" /><sdl:seg id=\"3\" origin=\"source\" /><sdl:seg id=\"4\" origin=\"source\" /></sdl:seg-defs></trans-unit>\r\n<trans-unit id=\"e261fc09-72ba-479f-9b50-64f99aa433cf\" translate=\"no\"><source>\r\n<x id=\"14\" />\r\n<x id=\"15\" />\r\n</source></trans-unit></group><group><sdl:cxts><sdl:cxt id=\"1\" /></sdl:cxts>\r\n<trans-unit id=\"58da8345-6ad8-4681-9819-573eb9bf5c01\"><source><g id=\"pt6\"><g id=\"pt7\">Notice 2011-36, posted today on IRS.gov, solicits public input and comment on several issues that will be the subject of future proposed guidance as Treasury and the IRS work to provide information to employers on how to comply with the shared responsibility provisions.  In particular, the notice requests comment on possible approaches employers could use to determine who is a full-time employee. </g></g></source><seg-source><g id=\"pt6\"><g id=\"pt7\"><mrk mtype=\"seg\" mid=\"5\">Notice 2011-36, posted today on IRS.gov, solicits public input and comment on several issues that will be the subject of future proposed guidance as Treasury and the IRS work to provide information to employers on how to comply with the shared responsibility provisions.</mrk>  <mrk mtype=\"seg\" mid=\"6\">In particular, the notice requests comment on possible approaches employers could use to determine who is a full-time employee.</mrk> </g></g></seg-source><target><g id=\"pt6\"><g id=\"pt7\"><mrk mtype=\"seg\" mid=\"5\">Notice 2011-36, posted today on IRS.gov, solicits public input and comment on several issues that will be the subject of future proposed guidance as Treasury and the IRS work to provide information to employers on how to comply with the shared responsibility provisions.</mrk>  <mrk mtype=\"seg\" mid=\"6\">In particular, the notice requests comment on possible approaches employers could use to determine who is a full-time employee.</mrk> </g></g></target><sdl:seg-defs><sdl:seg id=\"5\" origin=\"source\" /><sdl:seg id=\"6\" origin=\"source\" /></sdl:seg-defs></trans-unit>\r\n<trans-unit id=\"d97b2d77-8c2a-4548-b673-3f3ea742c1bb\" translate=\"no\"><source>\r\n<x id=\"20\" />\r\n<x id=\"21\" />\r\n</source></trans-unit></group><group><sdl:cxts><sdl:cxt id=\"1\" /></sdl:cxts>\r\n<trans-unit id=\"92d05960-0900-430c-8b4f-b75f9637a053\"><source><g id=\"pt8\"><g id=\"pt9\">Today’s request for comment is designed to ensure that Treasury and IRS continue to receive broad input from stakeholders on how best to implement the shared responsibility provisions in a way that is workable and administrable for employers, allowing them flexibility and minimizing  burdens.  Employers have asked for guidance on this provision, and a number of stakeholder groups have approached Treasury and IRS with information and initial suggestions, which have been taken into account in developing today’s notice.  By soliciting comments and feedback now, Treasury and IRS are giving all interested parties the opportunity for input before proposed regulations are issued at a later date.</g></g></source><seg-source><g id=\"pt8\"><g id=\"pt9\"><mrk mtype=\"seg\" mid=\"7\">Today’s request for comment is designed to ensure that Treasury and IRS continue to receive broad input from stakeholders on how best to implement the shared responsibility provisions in a way that is workable and administrable for employers, allowing them flexibility and minimizing  burdens.</mrk>  <mrk mtype=\"seg\" mid=\"8\">Employers have asked for guidance on this provision, and a number of stakeholder groups have approached Treasury and IRS with information and initial suggestions, which have been taken into account in developing today’s notice.</mrk>  <mrk mtype=\"seg\" mid=\"9\">By soliciting comments and feedback now, Treasury and IRS are giving all interested parties the opportunity for input before proposed regulations are issued at a later date.</mrk></g></g></seg-source><target><g id=\"pt8\"><g id=\"pt9\"><mrk mtype=\"seg\" mid=\"7\">Today’s request for comment is designed to ensure that Treasury and IRS continue to receive broad input from stakeholders on how best to implement the shared responsibility provisions in a way that is workable and administrable for employers, allowing them flexibility and minimizing  burdens.</mrk>  <mrk mtype=\"seg\" mid=\"8\">Employers have asked for guidance on this provision, and a number of stakeholder groups have approached Treasury and IRS with information and initial suggestions, which have been taken into account in developing today’s notice.</mrk>  <mrk mtype=\"seg\" mid=\"9\">By soliciting comments and feedback now, Treasury and IRS are giving all interested parties the opportunity for input before proposed regulations are issued at a later date.</mrk></g></g></target><sdl:seg-defs><sdl:seg id=\"7\" origin=\"source\" /><sdl:seg id=\"8\" origin=\"source\" /><sdl:seg id=\"9\" origin=\"source\" /></sdl:seg-defs></trans-unit>\r\n<trans-unit id=\"612c902a-2e35-40a0-a885-1973cac2c228\" translate=\"no\"><source>\r\n<x id=\"26\" />\r\n<x id=\"27\" />\r\n</source></trans-unit></group><group><sdl:cxts><sdl:cxt id=\"1\" /></sdl:cxts>\r\n<trans-unit id=\"f478631d-d40a-4649-9874-63364681a6d2\"><source><g id=\"pt10\"><g id=\"pt11\">Consistent with the coordinated approach the Departments of Treasury, Labor, and Health and Human Services are taking in developing the regulations and other guidance under the Affordable Care Act, the notice also solicits input on how the three Departments should interpret and apply the Act’s provisions limiting the ability of plans and issuers to impose a waiting period for health coverage of longer than 90 days starting in 2014.  In addition, the notice invites comment on how guidance under the 90-day provisions should be coordinated with the rules Treasury and IRS will propose regarding the shared responsibility provisions.</g></g></source><seg-source><g id=\"pt10\"><g id=\"pt11\"><mrk mtype=\"seg\" mid=\"10\">Consistent with the coordinated approach the Departments of Treasury, Labor, and Health and Human Services are taking in developing the regulations and other guidance under the Affordable Care Act, the notice also solicits input on how the three Departments should interpret and apply the Act’s provisions limiting the ability of plans and issuers to impose a waiting period for health coverage of longer than 90 days starting in 2014.</mrk>  <mrk mtype=\"seg\" mid=\"11\">In addition, the notice invites comment on how guidance under the 90-day provisions should be coordinated with the rules Treasury and IRS will propose regarding the shared responsibility provisions.</mrk></g></g></seg-source><target><g id=\"pt10\"><g id=\"pt11\"><mrk mtype=\"seg\" mid=\"10\">Consistent with the coordinated approach the Departments of Treasury, Labor, and Health and Human Services are taking in developing the regulations and other guidance under the Affordable Care Act, the notice also solicits input on how the three Departments should interpret and apply the Act’s provisions limiting the ability of plans and issuers to impose a waiting period for health coverage of longer than 90 days starting in 2014.</mrk>  <mrk mtype=\"seg\" mid=\"11\">In addition, the notice invites comment on how guidance under the 90-day provisions should be coordinated with the rules Treasury and IRS will propose regarding the shared responsibility provisions.</mrk></g></g></target><sdl:seg-defs><sdl:seg id=\"10\" origin=\"source\" /><sdl:seg id=\"11\" origin=\"source\" /></sdl:seg-defs></trans-unit>\r\n<trans-unit id=\"2fe34b15-aab0-4c62-8f3a-ffcb2f0e18a6\" translate=\"no\"><source>\r\n<x id=\"32\" />\r\n<x id=\"33\" />\r\n\r\n<x id=\"34\" />\r\n<x id=\"35\" />\r\n\r\n<x id=\"36\" /></source></trans-unit></group></body></file></xliff>','e9e270a6ac08d939d0d4c2e0531b6c788866ecff','�]oW��z��$�n�l�����m��DI�Dc;�m�ک�D?﮽[�Wwv�n�C�)UxA��. ��@\0�\0U@_�P��VB�JU�����^�ǽ;�����cQ{�;w�ι�|����z�?�U�=h��6����j3��Q��j۬�뺹ޅ�)�D�d�0گ�|��k?�X����,{����XbX�a²�0��`9�ƈ���}�(�&\\��ex���1����	6��.������+�#X�4�i����!,�U�cm����O�|π�P_?��\0��+\\$�]�&�E��\\1�˩���j5�.T�\'./R�t1ɞ@�����������~|��eĶ!�^7��|=RTB���\Z�s�Zr�F+a~KC\n�2��T0�O���q&���\\\\�25w\0�3Ѱ��k3,��Ba&�`yj�,|����p\'�`�!c(�f9˦�ƫ�#ElIE� �۸	C�З00z��v!���CLZH�s0�����PFɊ(I��ZS ��:�6��H����3@q~��R�$c�P[a���0�F���\Z�L!�6�Y�#�W�7��󸻑���tĠH3OF��,�	��\Zɞ��I��s���1ل%-�?�Y�%���A�U�%E]\rbINZ_7 \0<��Y]&<�u\rk��(dߟ�q�x�\"N�yX9|:�@0���tYV�p|ͯ�s<�q���?��0�%�|,C3G_���4��|��ˢ\Z7x�K�}��фLr��5����s8X�N4\'���0��͞a+ډ��t?1��X���M�a���޶�^��H��4�>Ot�b~[���6\Zi���uz�Y\'wiO0�f�~����x>��K��7ޙ��o������T��\Z��:�q��Z\nd�V��O\0>��\'T?\rm������������~�G��E_�!�?����������:�P%\n��ڻ�s��k�q��G���4M�1�NYŜU83�޷?<	�y�sr\'��y.7���^���$�����!��o�D�?U�a?��W���>p+\0�^pm���]U�N����)�;~(]�B�j����E�\0��V�a�Z�cmi�}��L�3��W�t���^	[ʸy���m�,�B���2��F<�Ok�W���:	0��hl��,��<J�Om����1��gy]:?1��ʶ��� ��`EѠ��dcm�� ������Y��ej�Ό�̭�Nkl�G�&9��R�d�M�<n���d�$[R�OS�*�O2���jL5ǚ,�_���I��	�4�OEġ76�TQ6�2�	\\��/�8���6�[�t��\Z�)�w����㘈WT��QC$����q�b�R,qA�*Qd�c˓S���L�H���Bh���fy��<�O.1���l�odd�Ze��Y\Z�\'��+�Dyo��+�s�g���M<#\r�늲^�5���͚�zMY���?��SĂ��3�x�2��1�W�&�ʊ���~�[[�ys98	���H�N>p��5�9fGZa.FYE�1�J?�Y�x�w���,t8�=*���+~�_�\\Ct�iHO-Z�pF���E%�ȸ�bޒʻl��ƚ{MOtA�P����S�F)�#\rH4�A2��a{ź��`]k���t�r��NeZ��Y����`�#�̶ִ\nM^�<���ɾ�u�&�4]R�X�\'���v�Ј�	�Ve�i���e̳�Ӭ�����l$�H{�����z�s�3�%�eZ)�ˢW���Ҭc텎/w�2�]I֞�5U��ԏ��U�fI�Qϐ:方=zޢLA�ɩ��ߖ\Z�V�EOi��;�u:G��j*�8����\\~#:\"i��Y����vj��Q��\Z8��RO\Za��MUS��y����0�l-�t�×�ف쾪t��m�G:[t�F�tb���َe�y���D�̱Uj�56itu2S���{�����+����b�\Z˞�QI�4�g��\ZU��#�ޓ�ԟlRrY�9��N,9��6�v�ws.�]2.3�)䕸YU#�X*]��\nd��BM�=DҴ�:g\r�s�3o��82�]Ui�9p�Ν3H���=gR�K9�:3�xd�/_�.��iU2:c�b��9��,�R��j���N��d�e�U.��?.���j�k_��\n�c`�^˭\r���B\'/�;�W��w���=��x���j_�=�u�=���Y��^z�w|[�$���o޵i��]�y�x�7E��k��y�k�Y�_�Ul9�D�3��)������~�)�#����)�v����D~�Rg}G�fJq��o��k�)>?������}�����A��=O���p�e��Ds��qh5h���NW>����]��0��^�\r��A��~�X����?�\"x�M����S���m,0v.F`�Q�Л��!x�E\0�p:�\"0?�+X&�`OD�!B��m�}��������C.����֟���^v?������@H�B,uo�z�I,�²o�=�\0H�\'��>��#|�k{�\Z��7�3n�����o�#X>�N`9�\0p�은.ܽ����Sx�~C�է���\'��S \'��|J�U�A��\n��f4 hJҥ5�va\0�]�)(}�wH�Ǖ���l�*�\Z��	��7�?v��Z��kg�mO+��ӑ�p���J��A����X\'�Y���6�\'���-��Qޚ>�l���3�V�����');
/*!40000 ALTER TABLE `files` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `files_job`
--

LOCK TABLES `files_job` WRITE;
/*!40000 ALTER TABLE `files_job` DISABLE KEYS */;
INSERT INTO `files_job` VALUES (4992,5300,NULL,NULL,NULL,NULL,NULL,'NEW');
/*!40000 ALTER TABLE `files_job` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=MyISAM AUTO_INCREMENT=4993 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
INSERT INTO `jobs` VALUES (4992,'ch29w8de',4719,'',NULL,'en-US','it-IT',NULL,NULL,NULL,NULL,1,1,'2013-10-09 16:03:29',0,'','active',NULL,'active','\0');
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

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

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `original_files_map`
--

LOCK TABLES `original_files_map` WRITE;
/*!40000 ALTER TABLE `original_files_map` DISABLE KEYS */;
INSERT INTO `original_files_map` VALUES ('e9e270a6ac08d939d0d4c2e0531b6c788866ecff','en-US','it-IT','�]oW��z��$�n�l�����m��DI�Dc;�m�ک�D?﮽[�Wwv�n�C�)UxA��. ��@\0�\0U@_�P��VB�JU�����^�ǽ;�����cQ{�;w�ι�|����z�?�U�=h��6����j3��Q��j۬�뺹ޅ�)�D�d�0گ�|��k?�X����,{����XbX�a²�0��`9�ƈ���}�(�&\\��ex���1����	6��.������+�#X�4�i����!,�U�cm����O�|π�P_?��\0��+\\$�]�&�E��\\1�˩���j5�.T�\'./R�t1ɞ@�����������~|��eĶ!�^7��|=RTB���\Z�s�Zr�F+a~KC\n�2��T0�O���q&���\\\\�25w\0�3Ѱ��k3,��Ba&�`yj�,|����p\'�`�!c(�f9˦�ƫ�#ElIE� �۸	C�З00z��v!���CLZH�s0�����PFɊ(I��ZS ��:�6��H����3@q~��R�$c�P[a���0�F���\Z�L!�6�Y�#�W�7��󸻑���tĠH3OF��,�	��\Zɞ��I��s���1ل%-�?�Y�%���A�U�%E]\rbINZ_7 \0<��Y]&<�u\rk��(dߟ�q�x�\"N�yX9|:�@0���tYV�p|ͯ�s<�q���?��0�%�|,C3G_���4��|��ˢ\Z7x�K�}��фLr��5����s8X�N4\'���0��͞a+ډ��t?1��X���M�a���޶�^��H��4�>Ot�b~[���6\Zi���uz�Y\'wiO0�f�~����x>��K��7ޙ��o������T��\Z��:�q��Z\nd�V��O\0>��\'T?\rm������������~�G��E_�!�?����������:�P%\n��ڻ�s��k�q��G���4M�1�NYŜU83�޷?<	�y�sr\'��y.7���^���$�����!��o�D�?U�a?��W���>p+\0�^pm���]U�N����)�;~(]�B�j����E�\0��V�a�Z�cmi�}��L�3��W�t���^	[ʸy���m�,�B���2��F<�Ok�W���:	0��hl��,��<J�Om����1��gy]:?1��ʶ��� ��`EѠ��dcm�� ������Y��ej�Ό�̭�Nkl�G�&9��R�d�M�<n���d�$[R�OS�*�O2���jL5ǚ,�_���I��	�4�OEġ76�TQ6�2�	\\��/�8���6�[�t��\Z�)�w����㘈WT��QC$����q�b�R,qA�*Qd�c˓S���L�H���Bh���fy��<�O.1���l�odd�Ze��Y\Z�\'��+�Dyo��+�s�g���M<#\r�늲^�5���͚�zMY���?��SĂ��3�x�2��1�W�&�ʊ���~�[[�ys98	���H�N>p��5�9fGZa.FYE�1�J?�Y�x�w���,t8�=*���+~�_�\\Ct�iHO-Z�pF���E%�ȸ�bޒʻl��ƚ{MOtA�P����S�F)�#\rH4�A2��a{ź��`]k���t�r��NeZ��Y����`�#�̶ִ\nM^�<���ɾ�u�&�4]R�X�\'���v�Ј�	�Ve�i���e̳�Ӭ�����l$�H{�����z�s�3�%�eZ)�ˢW���Ҭc텎/w�2�]I֞�5U��ԏ��U�fI�Qϐ:方=zޢLA�ɩ��ߖ\Z�V�EOi��;�u:G��j*�8����\\~#:\"i��Y����vj��Q��\Z8��RO\Za��MUS��y����0�l-�t�×�ف쾪t��m�G:[t�F�tb���َe�y���D�̱Uj�56itu2S���{�����+����b�\Z˞�QI�4�g��\ZU��#�ޓ�ԟlRrY�9��N,9��6�v�ws.�]2.3�)䕸YU#�X*]��\nd��BM�=DҴ�:g\r�s�3o��82�]Ui�9p�Ν3H���=gR�K9�:3�xd�/_�.��iU2:c�b��9��,�R��j���N��d�e�U.��?.���j�k_��\n�c`�^˭\r���B\'/�;�W��w���=��x���j_�=�u�=���Y��^z�w|[�$���o޵i��]�y�x�7E��k��y�k�Y�_�Ul9�D�3��)������~�)�#����)�v����D~�Rg}G�fJq��o��k�)>?������}�����A��=O���p�e��Ds��qh5h���NW>����]��0��^�\r��A��~�X����?�\"x�M����S���m,0v.F`�Q�Л��!x�E\0�p:�\"0?�+X&�`OD�!B��m�}��������C.����֟���^v?������@H�B,uo�z�I,�²o�=�\0H�\'��>��#|�k{�\Z��7�3n�����o�#X>�N`9�\0p�은.ܽ����Sx�~C�է���\'��S \'��|J�U�A��\n��f4 hJҥ5�va\0�]�)(}�wH�Ǖ���l�*�\Z��	��7�?v��Z��kg�mO+��ӑ�p���J��A����X\'�Y���6�\'���-��Qޚ>�l���3�V�����','�}ɒ�X��>#��\\�\'��B��@ B����#!4�Y��!�����G�\'�G�i�]鲫��VwUWV8Ag���������8\Z� /�4��	{�>\r@�n��??U�7b����6\n<o\0�&��~~:�e�A��wN\Z#� �.��ѡo�܆z50�4���9�mD8O��O�\"(�\'V�����6�{7u�$�����f��$$�_0���~)s�M�_��Lc��S���8e��l�p4:BY����l�!G�Qj�t�[�_@2ҵ_�SP�yZ�$\0ҝV�F���僟�n͞�UZ%���vy�ԕ �r�B��!d��r��:�H�AFN�rA��C<�Cy���v�l�nLzi��d[�ɧ����H��o4}��ZI.|��h�o��n�;�y6�c�L��2ٖ�\Z����\\��!V0��ú�\\v�-k���se4|X\nb[3�`�6�N�Vl��l���캢¥��Q�K�0���ki�[<>,�La���s��I���$�ƆN����T�J�e\0�M�0���3��VH�6�Ґ&������a~��e��r^��$D�I5BNd��i���>��6��z�k[���CiTjh\rm$��	)��W��A���h�>ۂ?�5`l��\nNǡ�%Og^m�R	­5�OҞr���;1�#Ln��q��P~����5/����\';^��6�W�NnW�Ɲ�É�ZL�b��HB�c��(	���L��)�c�Ij6�x�� I�$�`c,��ߓ�8�N�)��`%G�dCRA�\\v,Gm��K�Q�{ޞYR�N\"Y��zN��0��m_�;��7�\\�d���^~jz��yG��v���c!��ԫ��̳C1؞Q���i�}|/�2}��\\�R���L?�+9]�g]�|��f��y�q1#O��u�����7��~�,�l�!�7�1�G]\n\'Ǻ5V�yA��B:���X��	4Q�9�uj��,������!G�q�8��H5\Z]y|�?>���hw��\"���j2;ʉ��D[ȧ@\rf�\\��ٜ�X�e�s��cnuO���i�fe{���񺶛\0��&��bX\rhV]��ޞ�3�b�<>0�\n,�eBEFR��u�<�h,Ɯ��A>R�WnعPV-��j���Y�Ώ׍��3�U��!wk\\���u���9��t兗-�XJ�A{�ศ�]���6�Pc)\r(�r��\'�(&�\Z*��rZ�K�դt�cc\n�*�գdI��{|�:e���v.��&1�#�c�5��켜#��W�cM@.c�#1;���\r^-NGr��Aء�.\0�||Δ2Ću\\wZm+KX������M7���́��w�YZ��x�\r����[��B�ڶr�)��i��@ow�;���y�fJib�!��0+r�WN56A�Y[UqbZd�e�\'	ڦH�t]e�Kj#[��au���(_��Zv�NfD3����Z�k3Q��&)�鄙ɉ�E���Ҙ�3�V�KI?�y��=>�FP�z��#j[���:�6��U:��S�)�͚���h�Kƌ��q(o���,1f|��+���i�21ue��|���7s�\Z�q�����|A�|D-\n��ˈ�(�����ʉb�/+kzZ	T�l+��@;WJf\r�2��Ɠ(�w��v#�rjMY^Ȕ����z:��&��h��`%(�qV,pf\\�H�o������0k����Z=^�s����4��-�^\0?�	���&u�CF�0�%,c$�`|�\r���a�����bWx����XM��*]8�攐�g����)��\0i�x���XZ\\O[,^E%����\"�.�T�6Vl��v�e�V�×W����mk����giߕl���g�b۽���t�h�Bz�^�� ��Xb��N���L߭��qWͮ����:�/��I�8��n��!.L��]G�˔e�G�qc�X�-Z}�F�J,�KYE��`�V�ec�Q�F�������&p�VGV��,CWG�9S��fj{�@7���8��s-{�h����|2K�T��&Cb��2��y�%-­g��k�V�F�I;��r����/v�u�TqN/�fq\n��O��$��L.���i~j����ˬ�Yġ�=��^��$A,]=\r�����%#�H�p�{�v�糵v�1�e�3�/#i���`s��H���ڵ���5�2d+&696�*w�/�kpd���g�ðb�QL���3�Q��k�3�9x�AhL�y(>��s�oH[\'�-p���YwF�0R�j�X�#2a��Zܐ�$���|�^W��rai�H�}���C\nu�A�\"Xflt�jlU�C��0-$��2�\n�LRĭ�̲;�l�2D���q����u;;����T[�Y\"��O��j\'��P�y�k�Lȷ+v���f�p��0�O�(���� j\0������(��0^����\Z��%�J3�%��}�ćk-1�G�Wq�첃y]^_l������$y��uH<��X2/�)���b(�����	�;�Ր�kC@EU���+���,E�\'��/�Բ��܎�K�Beؙ���}Fj�;ܘÄPU�h��J݉Q�\\����Z-s���w���	)��1�꒛�GvL=�9��5�mڵ��cԺ� Yn���o��;:\'q�&���p�����bK�h2�03Sel -ua^ؖ�5�M�\rka�r�_l�J�lk�cgi��ӆ��p��y�0�7�\\.�\"��|�y���g�7!�G�ŗ	�L�ڇ�Fkt��`�#�a�̀��?��/���ȫe�f�0��\nA9_*��6i_F��+{�7���1���[���8�l�`�}�#F2��U�K�:O�`s��V3��MZ)Z�%�\n�ʐۮ��!�7���nR1�<kuu�����=��m)<>�\Z������ݍ�9��f���S�O�����sBjrL�Xb[��]p�2N���Ԫޭ���!j�:\Z�7Dv���+�S�:�ѫ��4�}d����ϣ�a$�h<�q��ĉ��jτ�Zm��g��㊖�֋U_Wc�m7杆a�l�F�X��t���9$�3W���$Izl��I��R�YJ\n��!k�.�������> u�PK����_��Z\"��\Z^e�\0>hV�=]�O��0]kL\n�mb�kd�\n�F�0{�d�]���H]G{�V23�܎�fg��LG)C?\n�#r��XOmb� Q\Z���C+�;���5�t����K ��0RyѮ�0�9XT��V��!����h\r��l�P���4\n�w\"��V��RNr����Ӷ�5��U,#婖Q%:L�-&ؙDVI��1�}�m�c8���Q�q��e㣀Q����\r�3�D�����E�yl��I����#�;���=\'�I���r���;W�������Ĳ��%�N���^VS۾�]wPb�27�(�7����\rz:��3�X`���ڢ{ڡ�ᴆH����3wN�Jֹ�\\|^��\r���J1_[��l�WuC��/LK~���j���P��D�,�gj6�ʅ��Q��Fʮg/�׾l.�S�<)Z{(,�7Y��9%l�dJe.��pq��!��)���^9��F�@�d����W%�&��z�?�:�&���9�����\'3$�3��p4�3�)m�\n�Z��lHl�B͖Ɨ,���e�I�U���M!�qR�s��E<MH�>TY�A4I�FC�VX�嘘c[�^y{�������y�A;s�Ѭ��w��eg�Z�K�>�l�z	R/R!�0�-�`d���y�@d��\rn���:W\'�t(q�<����u�L�1^�&䰣-6J�K���5ېW�=C���\r�U���~B����	�f͖���ؒA��\"�f}<��#�qi���5�]�@Z\rm���6[�A��`��P�Lx�|L�6:��CI��p�>�V�RN�׺�ZM�2�b���iq��Ξc�y�K���˅3��NO�`?�X|�.6�=Z݄vF\Z��>V�[b��� 6�8p]J=�(���MUף�D{.3�����z���e��kM��9_sF5��-v�����[ow�M��uA=>ؕ�-&������!X�ބhj��-\\�,�uZ��v�֒ɴ|Z̬)��p�_0W\rœm=n�\ZG#E����OL�Q�N4j��䖢��p%?JV�8��k��\'-��%��4�,�ʦ0���H�����yL�A�8����M1�9e���T&���B�ÊwO\Z\"���.$:=1�s�-�J/]���l�Er�W�u��˅ Z��|݃�NN�#C:�L��K-�J�e��&0�\n�b�3� tޢ�z��%w�*�Ӱ��Ԝ��\\8o/��qW�:����ZMS���?��a~����!Y�L��t-�:�>>�E\"�$�XDZ��W�s��K����֒��_�Yيt��C˳�Ň	#�=�LP*85p�p�֊*/|�\nH�����+H���Mi׬�W8��`9�9}��j����Թ���LKॳy| �\Z0|�Պ�.>�#�K_�\Z٠1�,�<w4qXT��FB\'͙�nn�I�R�:��2���,�i1?`��DV�_���<��z���DU\Z��F����4M���ח=\Z��^ѵ=�Κ��&(�ZM��vm��e�!��q�L5���\"�*�~݇�-5I�]b�u�^	�6ck)S��b�-b�	�.�Ü�i�;�镒��rZ������o�eT�L	�y���Y)E/�Ӗ*�����#�6�B=ʰ\"�2*l�2��ⰃF�i�펋���X�ۥRv^E�0�8�dpɠ�\\�u��KN�+��P^�Ǉ�\"b7��E�;e���kN<�X7�a��[���0x�+������Z�T-�����BϢW%��:���R9nR�75��\"�e5�����IR[D����~�|C7��`#�M���܋�t�_���H�M�E�]=.\Zn��\Z���NӢ��M�e$��åzX8R\\f��\"�B��j>����Y(oO\Z~�J�a3����9[m�]�R���XT���5�pwgƥvDo����|����>���Z1�ᶲ9�/�Y7��K����帮�ǡ�ϸx�/�rj���j�X-��lli\'�T;�Ǉ|/�N���f�]k[W������M*�*\"� tb�T@��̍4<(�����s}ĠZ���gh!y�z�1�Ds�|���2��\rۤ��4�ƚ�20�u��>\rVf8�g���%}�	�`��PԚ^n�lͶ�0�Z��d���m�k��$�\\�\nG*�5��p-�Ύ�j��^��\n+�B�sGa�����Ls;�\n�>cK�2���5��<-6z�	wH��Lؒ�|��x��:\"�9��¶	�gN�l�scb�6\\`�&���d��]g&���\Z�n�PO��1��2V�����n��1-S9$���Ǉ�ɑ5Ȇ*ti1a��`c�����qL�����<��a(���#�ݒTh��u���Z�C欆����\r�2�|X.co+��de0\0Q��\Z!�>�D�%��؛7�\\�->7�#��*_�֋���HG悢U��B�S�H�p��B̉ϐ\r��\rۃ����C�c٥&����	r��F��4|��\ny�j�\nct�B/E𕱲X��)��jL�֑D�5\"O˄��C�@ �X&h�)���G�U��G����ꉳ�<��t�2�d(pp};��Y�W�?V!��Ht�q���q����?o�I��r�9�l��{�/a���?���κ�?k�������A�??�O���� �i�w/��\Z�|9�Ԫ,K�����T��Z��s	�|�:�N�$A�4�4~�R\"?�{�6޻2Ξ.(���{�����~��2���iP���TpV9(O`P1��\0�en9}�wO�!N	�l����S�O$ ���e�L��)�4���4@������\0���\"��\'+?,�w������*O�!�k��EP�Q�`�{O`�t�B�]6��@	�(zm��Moh�z���Zy�	(t�+��?FA�/��?v���CmE����I�,���$��#�\0��}�X�A�#�#�I�`h˥)�r��1���@)��^I�~��1�[_+[�\n}���(�[��p&!J��\"��Wh�wy��i6�Ԩ�|HPO�%*�uYduB\Zgh5\'����/+�AW��I�=���忮=����}��|��e�:wAޗ��l	�v��Pr��?��n�6�)~ݺ/��i��%�&n�!T�Xy�0 )o\n�\rn�~G���\n�o����u�盩\'+�A1�7�,�m%?��s��G���� ���+��m�7��9t��~��Yߨ��4w\'�+`�*&��,���z��웍��P���ޤ�nC|]���fi]���7�蔔��e���E��8>m+}^ʑoUi�V�-���	���eo��ųv~��e�/K���`��\"�#���_�U���)�y_����_��e[�V�Է�&{J#�����߬����45�5}�������Z���Vv�}v��6t��K�\"���>~pAyO�n$\r�(5�`Z��!?\r��OW�n���Hȧ�rO��c�,r?�>�Q�_Ġ���w�Y�[~]r΋��3��Md��w\0��z-�`���b��`sH�_Lf.��^��OǑ償� �|I\n�-R\n+)Fȃ7���׷�TZQ�<}�YQ�f�WL��m�؊\"�ʠ�ޛ|���,Z��ǛfS�?\"�o�������x����Y\n�2\Z�\0��oq�G���7�)��?���=qp_/��������4��B���T�ǻ�~�%#��<��o������ߍ��������%��=\r @wG ��^orwh�x\n��ɖ�6�ܟ�z�܌~�`q���U��bz�5_>=�\\vB���\0R���>9���;��Ë��{L�̽���9����\"�&�ؗ,���7����+s{zf�+���)~o��@d��;ߜ�Uo0D������W�����W�����W�������-��*+^F|�a>=��)ʷ\0�4q��Y��u�a�t��A7����J+/|B�O*(��%�QRQ��-*���~8����hQ���V�dY��0�k���G+�wE��^��Ü���\rk�n����C��(��¸�I��XgF$ʀ	hvġ3�1��9%\\�׶��q����E��C�J P��#�\'�����*��z;mY|����}[�V�kI�cI\Z�F6j;�J��$��,�rP�a<�(z���jk�൹�w�������������r`U�\r&\0��O�V�^��[P�jBy8`P���\rrp�`�� �l����l�&��(`lE�.�q;�.N�HX\\diRve�v�A��v1\'�\\�$Hnxσ�Ѳ�-��w��	V9h�(\ZXYu���Kvq�����~fX�������ߋ�\n���^����y+�O��~�-�oHJ�E��dzM�o���d�\"�>d�r���秗�r�3P�7%|�1�;�Q�:�qܲݑ��ވt0{����ў��g��7�\"�}^���b�\"�(�<��0Zΰ�8�v!�8:�\0GB�q��h0�F�$_�L�zSƷ���+m��MQ(�� �/�xU�nW���@[���MI\n-������Aav��NZ������#��xmp�ߠ7G�8�B0��e�V������n�Y͠Ȁx�cE�\0A�.��N����ړUC^@s����A�;8jP|��A	�)\0����Eܯ�v���r��;6����X��Eq�i�1)�O�#z�������]���:�Y�u�-�<s��<�Fn[#��`*�)tD��YI���2F~��0��]�Z,AR#�r�I�؈c1nD1�K�}��E�r.���*-{�����A�ޠ��@uK[흟�?\r�b�\0��3\n\n����Wx�\0��D/��3���pF��_��o{UY���)�~�VI���H�����.�B_����u��~Q�/�����67��_l����#��O}R2��a�T���t��e���C�o��z����s�}�%NZEn?�\'����A�)������)��\Z��^�o�~���X(��b���c��o#�b�ϧ�w�%�gK�cl�e���0[R$;�i�,������~���/�%���a��l��.J�n�s(:\"	��6�l��8�`,�\"�Ȗ�+� �=�?��/����O�\0��\"�;�I���?�0��Н4����f9p@\0��\r��}�9/O�~u��*�+���Ya������G��Gj֠��;9����nض��r�Q��}�s�\r��$:mn�\'��/��]�qp��У�*w�=�N?y�\r�ZE��G�z��[�t�e�T�\rAk���f\0/��x��k�����W�^_�1��|\n��O��t��m�J�r��&7���7��쾽��{8��w/Q�o�l\n�mR\0׶��n~�5��B��[��o/�=o4���Z��no��v���w7��o��k�*��[�ƽ���[Y֠w���Nѻ�_��a���~���p���G8�w�\0-�O�K?���G��Q{�����^滨��n���4�;\n!1jD�:�X�\ZaC8��;8��\0.�������=�aisG.${D�$7�X��A�{J�ur�(���_��a���5�s�4w�����en�����^l��Ҳ�������k[�˩�݂��<�z������`��r�귷�_����H?���v��<�Q7_2R�n+���d9�/����N��o��*fF0��/�[�1�#��g�o�c�=C_��{��Az�i_oO�1�4�_6~9�W��a�}��r���:�+���J��:�g�\rAr��Fx�׳$�/-��Y��oNf�CF�F����P��A\\�+����2�9�)��;���_��z�m��_�迏��*������ic�ȲltD:4��-k�y��{(�X��>�#�/�A�?|y�HP_��ϔ�XDn�\Z�o�Ï������','2013-10-09');
/*!40000 ALTER TABLE `original_files_map` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=MyISAM AUTO_INCREMENT=4720 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `projects`
--

LOCK TABLES `projects` WRITE;
/*!40000 ALTER TABLE `projects` DISABLE KEYS */;
INSERT INTO `projects` VALUES (4719,'2ffm3qmb','translated_user','WhiteHouse_1.doc','2013-10-09 16:03:29',NULL,NULL,'NEW',0.00,0.00,0.00,'127.0.0.1',0);
/*!40000 ALTER TABLE `projects` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `segment_translations`
--

LOCK TABLES `segment_translations` WRITE;
/*!40000 ALTER TABLE `segment_translations` DISABLE KEYS */;
/*!40000 ALTER TABLE `segment_translations` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `segment_translations_analysis_queue`
--

LOCK TABLES `segment_translations_analysis_queue` WRITE;
/*!40000 ALTER TABLE `segment_translations_analysis_queue` DISABLE KEYS */;
/*!40000 ALTER TABLE `segment_translations_analysis_queue` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=MyISAM AUTO_INCREMENT=2356816 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `segments`
--

LOCK TABLES `segments` WRITE;
/*!40000 ALTER TABLE `segments` DISABLE KEYS */;
INSERT INTO `segments` VALUES (2356805,5300,'4df8461a-b0bc-4003-b411-ab5c0cc7ff0e','1','<g id=\"pt1\">','','<g id=\"pt2\">WASHINGTON </g><g id=\"pt3\">â€” The Treasury Department and Internal Revenue Service today requested public comment on issues relating to the shared responsibility provisions included in the Affordable Care Act that will apply to certain employers starting in 2014.</g>','','</g>',36.00,1),(2356806,5300,'3127871f-936d-4c20-ae94-de295a60e7f2','2','<g id=\"pt4\"><g id=\"pt5\">','','Under the Affordable Care Act, employers with 50 or more full-time employees that do not offer affordable health coverage to their full-time employees may be required to make a shared responsibility payment.','','',32.00,1),(2356807,5300,'3127871f-936d-4c20-ae94-de295a60e7f2','3','Â  ','','The law specifically exempts small firms that have fewer than 50 full-time employees.','','',13.00,1),(2356808,5300,'3127871f-936d-4c20-ae94-de295a60e7f2','4',' ','','This provision takes effect in 2014.','','</g></g>',6.00,1),(2356809,5300,'58da8345-6ad8-4681-9819-573eb9bf5c01','5','<g id=\"pt6\"><g id=\"pt7\">','','Notice 2011-36, posted today on IRS.gov, solicits public input and comment on several issues that will be the subject of future proposed guidance as Treasury and the IRS work to provide information to employers on how to comply with the shared responsibility provisions.','','',44.00,1),(2356810,5300,'58da8345-6ad8-4681-9819-573eb9bf5c01','6','Â  ','','In particular, the notice requests comment on possible approaches employers could use to determine who is a full-time employee.','','Â </g></g>',19.00,1),(2356811,5300,'92d05960-0900-430c-8b4f-b75f9637a053','7','<g id=\"pt8\"><g id=\"pt9\">','','Todayâ€™s request for comment is designed to ensure that Treasury and IRS continue to receive broad input from stakeholders on how best to implement the shared responsibility provisions in a way that is workable and administrable for employers, allowing them flexibility and minimizing<x id=\"nbsp\"/> burdens.','','',44.00,1),(2356812,5300,'92d05960-0900-430c-8b4f-b75f9637a053','8','Â  ','','Employers have asked for guidance on this provision, and a number of stakeholder groups have approached Treasury and IRS with information and initial suggestions, which have been taken into account in developing todayâ€™s notice.','','',34.00,1),(2356813,5300,'92d05960-0900-430c-8b4f-b75f9637a053','9','Â  ','','By soliciting comments and feedback now, Treasury and IRS are giving all interested parties the opportunity for input before proposed regulations are issued at a later date.','','</g></g>',27.00,1),(2356814,5300,'f478631d-d40a-4649-9874-63364681a6d2','10','<g id=\"pt10\"><g id=\"pt11\">','','Consistent with the coordinated approach the Departments of Treasury, Labor, and Health and Human Services are taking in developing the regulations and other guidance under the Affordable Care Act, the notice also solicits input on how the three Departments should interpret and apply the Actâ€™s provisions limiting the ability of plans and issuers to impose a waiting period for health coverage of longer than 90 days starting in 2014.','','',69.00,1),(2356815,5300,'f478631d-d40a-4649-9874-63364681a6d2','11','Â  ','','In addition, the notice invites comment on how guidance under the 90-day provisions should be coordinated with the rules Treasury and IRS will propose regarding the shared responsibility provisions.','','</g></g>',29.00,1);
/*!40000 ALTER TABLE `segments` ENABLE KEYS */;
UNLOCK TABLES;

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

LOCK TABLES `segments_comments` WRITE;
/*!40000 ALTER TABLE `segments_comments` DISABLE KEYS */;
/*!40000 ALTER TABLE `segments_comments` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `translators`
--

LOCK TABLES `translators` WRITE;
/*!40000 ALTER TABLE `translators` DISABLE KEYS */;
/*!40000 ALTER TABLE `translators` ENABLE KEYS */;
UNLOCK TABLES;

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

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2013-10-09 16:04:19
-- Drop user if already Exista
GRANT USAGE ON *.* TO 'unt_matecat_user'@'localhost';
DROP USER 'unt_matecat_user'@'localhost';
CREATE USER 'unt_matecat_user'@'localhost' IDENTIFIED BY 'unt_matecat_user';
GRANT ALL ON unittest_matecat_local.* TO 'unt_matecat_user'@'localhost' IDENTIFIED BY 'unt_matecat_user';
FLUSH PRIVILEGES;