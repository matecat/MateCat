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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8; 

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

CREATE TABLE `files` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_project` int(11) NOT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `source_language` varchar(45) NOT NULL,
  `mime_type` varchar(45) DEFAULT NULL,
  `xliff_file` longblob,
  `sha1_original_file` varchar(100) DEFAULT NULL,
  `original_file` longblob,
  `remote_id` text,
  PRIMARY KEY (`id`),
  KEY `id_project` (`id_project`),
  KEY `sha1` (`sha1_original_file`) USING HASH,
  KEY `filename` (`filename`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8; 

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
  `dqf_key` varchar(255) DEFAULT NULL,
  `avg_post_editing_effort` float DEFAULT '0',
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
  KEY `status_owner_idx` (`status_owner`),
  KEY `status_idx` (`status`),
  KEY `create_date_idx` (`create_date`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8; 

CREATE TABLE `language_stats` (
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `source` varchar(255) NOT NULL,
  `target` varchar(255) NOT NULL,
  `total_word_count` float(255,0) DEFAULT NULL,
  `total_post_editing_effort` float(255,0) DEFAULT NULL,
  `total_time_to_edit` float(255,0) DEFAULT NULL,
  `job_count` int(255) DEFAULT NULL,
  PRIMARY KEY (`date`,`source`,`target`),
  KEY `source_idx` (`source`),
  KEY `target_idx` (`target`),
  KEY `date_idx` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8; 

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

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `id_comment` int(11) NOT NULL,
  `id_translator` varchar(100) CHARACTER SET latin1 NOT NULL,
  `status` varchar(45) CHARACTER SET latin1 DEFAULT 'UNREAD',
  PRIMARY KEY (`id`),
  KEY `id_comment` (`id_comment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8; 

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

CREATE TABLE `owner_features` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` bigint(20) NOT NULL,
  `feature_code` varchar(45) NOT NULL,
  `options` text,
  `create_date` datetime NOT NULL,
  `last_update` datetime NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid_feature` (`uid`,`feature_code`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8; 

CREATE TABLE `phinxlog` (
  `version` bigint(20) NOT NULL,
  `start_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `end_time` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8; 

CREATE TABLE `project_metadata` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_project` bigint(20) NOT NULL,
  `key` varchar(255) NOT NULL,
  `value` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_project_and_key` (`id_project`,`key`) USING BTREE,
  KEY `id_project` (`id_project`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8; 

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
  `pretranslate_100` int(1) DEFAULT '0',
  `id_qa_model` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_customer` (`id_customer`),
  KEY `status_analysis` (`status_analysis`),
  KEY `for_debug` (`for_debug`),
  KEY `remote_ip_address` (`remote_ip_address`),
  KEY `name` (`name`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8; 

CREATE TABLE `qa_categories` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_model` bigint(20) NOT NULL,
  `label` varchar(255) NOT NULL,
  `id_parent` bigint(20) DEFAULT NULL,
  `severities` text COMMENT 'json field',
  PRIMARY KEY (`id`),
  KEY `id_model` (`id_model`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8; 

CREATE TABLE `qa_chunk_reviews` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_project` int(11) NOT NULL,
  `id_job` bigint(20) NOT NULL,
  `password` varchar(45) NOT NULL,
  `review_password` varchar(45) NOT NULL,
  `score` bigint(20) NOT NULL DEFAULT '0',
  `num_errors` int(11) NOT NULL DEFAULT '0',
  `is_pass` tinyint(4) NOT NULL DEFAULT '0',
  `force_pass_at` timestamp NULL DEFAULT NULL,
  `reviewed_words_count` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_job_password` (`id_job`,`password`),
  KEY `id_project` (`id_project`),
  KEY `review_password` (`review_password`),
  KEY `id_job` (`id_job`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8; 

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
  `penalty_points` int(11) NOT NULL,
  `comment` text,
  `replies_count` int(11) NOT NULL DEFAULT '0',
  `create_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `job_and_segment` (`id_job`,`id_segment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8; 

CREATE TABLE `qa_entry_comments` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` bigint(20) DEFAULT NULL,
  `id_qa_entry` bigint(20) NOT NULL,
  `create_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `comment` text,
  PRIMARY KEY (`id`),
  KEY `id_qa_entry` (`id_qa_entry`),
  KEY `create_date` (`create_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8; 

CREATE TABLE `qa_models` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` bigint(20) DEFAULT NULL,
  `create_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `label` varchar(255) DEFAULT NULL,
  `pass_type` varchar(255) DEFAULT NULL,
  `pass_options` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8; 

CREATE TABLE `segment_notes` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_segment` bigint(20) NOT NULL,
  `internal_id` varchar(100) NOT NULL,
  `note` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_segment` (`id_segment`) USING BTREE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8; 

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

CREATE TABLE `segment_translation_versions` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_segment` bigint(20) NOT NULL,
  `id_job` bigint(20) NOT NULL,
  `translation` text,
  `version_number` int(11) NOT NULL,
  `creation_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `propagated_from` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_segment` (`id_segment`) USING BTREE,
  KEY `id_job` (`id_job`) USING BTREE,
  KEY `creation_date` (`creation_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8; 

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

CREATE TABLE `segment_translations_splits` (
  `id_segment` bigint(20) NOT NULL,
  `id_job` bigint(20) NOT NULL,
  `source_chunk_lengths` varchar(1024) NOT NULL DEFAULT '[]',
  `target_chunk_lengths` varchar(1024) NOT NULL DEFAULT '{"len":[0],"statuses":["DRAFT"]}',
  PRIMARY KEY (`id_segment`,`id_job`),
  KEY `id_job` (`id_job`),
  KEY `id_segment` (`id_segment`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8; 

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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8; 

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

CREATE TABLE `users` (
  `uid` bigint(20) NOT NULL AUTO_INCREMENT,
  `email` varchar(50) NOT NULL,
  `salt` varchar(50) NOT NULL,
  `pass` varchar(50) NOT NULL,
  `create_date` datetime NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `api_key` varchar(100) NOT NULL,
  `oauth_access_token` text,
  PRIMARY KEY (`uid`),
  UNIQUE KEY `email` (`email`),
  KEY `api_key` (`api_key`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8; 


        INSERT INTO `engines` (
  `name` ,
  `type` ,
  `description` ,
  `base_url` ,
  `translate_relative_url` ,
  `contribute_relative_url`,
  `delete_relative_url` ,
  `others` ,
  `class_load`,
  `extra_parameters` ,
  `google_api_compliant_version` ,
  `penalty` ,
  `active` ,
  `uid`
)
VALUES
('NONE','NONE','No MT','','',NULL,NULL,'{}','NONE','',NULL,100,0,NULL),
(
'MyMemory (All Pairs)',
'TM',
'Machine translation from Google Translate and Microsoft Translator.',
'http://api.mymemory.translated.net',
'get',
'set',
'delete',
'{\"gloss_get_relative_url\":\"glossary\/get\",\"gloss_set_relative_url\":\"glossary\/set\",\"gloss_update_relative_url\":\"glossary\/update\",\"gloss_delete_relative_url\":\"glossary\/delete\",\"tmx_import_relative_url\":\"tmx\/import\",\"tmx_status_relative_url\":\"tmx\/status\",\"tmx_export_create_url\":\"tmx\/export\/create\",\"tmx_export_check_url\":\"tmx\/export\/check\",\"tmx_export_download_url\":\"tmx\/export\/download\",\"tmx_export_list_url\":\"tmx\/export\/list\",\"api_key_create_user_url\":\"createranduser\",\"api_key_check_auth_url\":\"authkey\",\"analyze_url\":\"analyze\",\"detect_language_url\":\"langdetect.php\"}',
'MyMemory',
'{}',
'1',
0,
1,
NULL);

UPDATE engines SET id = 0 WHERE name = 'NONE' ;
UPDATE engines SET id = 1 WHERE name = 'MyMemory (All Pairs)' ;

 INSERT INTO `phinxlog` ( version, start_time, end_time ) VALUES ( '20150918101657', '2016-02-18 14:28:18', '2016-02-18 14:28:18'); 

 INSERT INTO `phinxlog` ( version, start_time, end_time ) VALUES ( '20150921114813', '2016-02-18 14:28:29', '2016-02-18 14:28:29'); 

 INSERT INTO `phinxlog` ( version, start_time, end_time ) VALUES ( '20150922152051', '2016-02-18 14:28:39', '2016-02-18 14:28:39'); 

 INSERT INTO `phinxlog` ( version, start_time, end_time ) VALUES ( '20151001131124', '2016-02-18 14:28:45', '2016-02-18 14:28:45'); 

 INSERT INTO `phinxlog` ( version, start_time, end_time ) VALUES ( '20151120150352', '2016-02-20 09:55:27', '2016-02-20 09:55:27'); 

 INSERT INTO `phinxlog` ( version, start_time, end_time ) VALUES ( '20151123141623', '2016-02-20 09:55:27', '2016-02-20 09:55:27'); 

 INSERT INTO `phinxlog` ( version, start_time, end_time ) VALUES ( '20151126093945', '2016-02-20 09:55:27', '2016-02-20 09:55:27'); 

 INSERT INTO `phinxlog` ( version, start_time, end_time ) VALUES ( '20151204140144', '2016-02-20 09:55:27', '2016-02-20 09:55:27'); 

 INSERT INTO `phinxlog` ( version, start_time, end_time ) VALUES ( '20151219181543', '2016-02-20 09:55:27', '2016-02-20 09:55:27'); 

 INSERT INTO `phinxlog` ( version, start_time, end_time ) VALUES ( '20151229103454', '2016-02-20 09:55:27', '2016-02-20 09:55:27'); 

 INSERT INTO `phinxlog` ( version, start_time, end_time ) VALUES ( '20160108101432', '2016-02-20 09:55:27', '2016-02-20 09:55:27'); 

 INSERT INTO `phinxlog` ( version, start_time, end_time ) VALUES ( '20160115143225', '2016-02-20 09:55:27', '2016-02-20 09:55:27'); 

 INSERT INTO `phinxlog` ( version, start_time, end_time ) VALUES ( '20160116085841', '2016-02-20 09:55:27', '2016-02-20 09:55:27'); 

 INSERT INTO `phinxlog` ( version, start_time, end_time ) VALUES ( '20160120143540', '2016-02-23 13:13:09', '2016-02-23 13:13:09'); 

 INSERT INTO `phinxlog` ( version, start_time, end_time ) VALUES ( '20160121170252', '2016-02-18 14:28:45', '2016-02-18 14:28:45'); 

 INSERT INTO `phinxlog` ( version, start_time, end_time ) VALUES ( '20160124101801', '2016-02-23T13:34:57+00:00', '2016-02-23T13:34:57+00:00'); 
