<?php

class AlterTableJobsForRawWc extends AbstractMatecatMigration {

    public $sql_up = [ "
        alter table jobs
            drop column `revision_stats_typing_min`,
            drop column `revision_stats_translations_min`,
            drop column `revision_stats_terminology_min`,
            drop column `revision_stats_language_quality_min`,
            drop column `revision_stats_style_min`,
            drop column `revision_stats_typing_maj`,
            drop column `revision_stats_translations_maj`,
            drop column `revision_stats_terminology_maj`,
            drop column `revision_stats_language_quality_maj`,
            drop column `revision_stats_style_maj`,
            drop column `dqf_key`,
            add column `approved2_words`      float(10, 2) NOT NULL DEFAULT '0.00',
            add column `new_raw_words`        float(10, 2) NOT NULL DEFAULT '0.00',
            add column `draft_raw_words`      float(10, 2) NOT NULL DEFAULT '0.00',
            add column `translated_raw_words` float(10, 2) NOT NULL DEFAULT '0.00',
            add column `approved_raw_words`   float(10, 2) NOT NULL DEFAULT '0.00',
            add column `approved2_raw_words`  float(10, 2) NOT NULL DEFAULT '0.00',
            add column `rejected_raw_words`  float(10, 2) NOT NULL DEFAULT '0.00',
            ALGORITHM=INPLACE, LOCK=NONE;
    " ];

    public $sql_down = [
            "
        alter table jobs
            add column `revision_stats_typing_min`           int(11)             NOT NULL DEFAULT '0',
            add column `revision_stats_translations_min`     int(11)             NOT NULL DEFAULT '0',
            add column `revision_stats_terminology_min`      int(11)             NOT NULL DEFAULT '0',
            add column `revision_stats_language_quality_min` int(11)             NOT NULL DEFAULT '0',
            add column `revision_stats_style_min`            int(11)             NOT NULL DEFAULT '0',
            add column `revision_stats_typing_maj`           int(11)             NOT NULL DEFAULT '0',
            add column `revision_stats_translations_maj`     int(11)             NOT NULL DEFAULT '0',
            add column `revision_stats_terminology_maj`      int(11)             NOT NULL DEFAULT '0',
            add column `revision_stats_language_quality_maj` int(11)             NOT NULL DEFAULT '0',
            add column `revision_stats_style_maj`            int(11)             NOT NULL DEFAULT '0',
            add column `dqf_key`                             varchar(255)                 DEFAULT NULL, 
            drop column `approved2_words`      ,
            drop column `new_raw_words`        ,
            drop column `draft_raw_words`      ,
            drop column `translated_raw_words` ,
            drop column `approved_raw_words`   ,
            drop column `approved2_raw_words`  ,
            drop column `rejected_raw_words`,
            ALGORITHM=INPLACE, LOCK=NONE;
    "
    ];
}