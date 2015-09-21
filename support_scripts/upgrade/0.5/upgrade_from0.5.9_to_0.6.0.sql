alter table jobs
change column c_a_delivery_date avg_post_editing_effort  FLOAT default 0,
change column c_delivery_date total_time_to_edit BIGINT default 0;

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