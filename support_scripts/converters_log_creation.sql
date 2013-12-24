CREATE DATABASE IF NOT EXISTS `matecat_conversions_log` /*!40100 DEFAULT CHARACTER SET utf8 */

DROP TABLE IF EXISTS `failed_conversions_log`;

CREATE TABLE `failed_conversions_log` (
  `ip_machine` varchar(15) NOT NULL,
  `ip_client` varchar(15) NOT NULL DEFAULT 'unknown',
  `path_backup` varchar(512) NOT NULL,
  `direction` varchar(5) NOT NULL DEFAULT 'fw' COMMENT 'Directions: fw (forward), bw (backward)',
  `error_message` varchar(255) DEFAULT NULL,
  `conversion_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `src_lang` varchar(15) DEFAULT NULL,
  `trg_lang` varchar(15) DEFAULT NULL,
  `status` varchar(2) NOT NULL DEFAULT 'ok',
  KEY `ip_machine_idx` (`ip_machine`),
  KEY `direction_idx` (`direction`),
  KEY `path_backup_idx` (`path_backup`(333)),
  KEY `src_lang_idx` (`src_lang`),
  KEY `trg_lang_idx` (`trg_lang`),
  KEY `conversion_date_idx` (`conversion_date`),
  KEY `status_idx` (`status`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Conversion Failure Log'


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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;

LOCK TABLES `converters` WRITE;
/*!40000 ALTER TABLE `converters` DISABLE KEYS */;
INSERT INTO `converters` VALUES
(1,'10.11.0.10','10.11.0.11','10.30.1.250','root','marcofancydandy','TradosAPI-2','2013-11-15 12:26:28',0,1,0),
(2,'10.11.0.18','10.11.0.19','10.30.1.251','root','marcofancydandy','TradosAPI-3','2013-11-15 14:14:08',1,0,0),
(3,'10.11.0.26','10.11.0.27','10.30.1.251','root','marcofancydandy','TradosAPI-4','2013-11-15 14:14:13',1,0,0),
(4,'10.11.0.34','10.11.0.35','10.30.1.232','root','marcofancydandy','TradosAPI-5','2013-11-15 14:14:16',1,0,0),
(5,'10.11.0.42','10.11.0.43','10.30.1.232','root','marcofancydandy','TradosAPI-6','2013-11-15 14:14:20',1,0,0);
/*!40000 ALTER TABLE `converters` ENABLE KEYS */;
UNLOCK TABLES;


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
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


