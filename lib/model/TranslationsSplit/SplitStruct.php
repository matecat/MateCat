<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 23/02/15
 * Time: 14.54
 */
class TranslationsSplit_SplitStruct extends DataAccess_AbstractDaoObjectStruct implements DataAccess_IDaoStruct {

    public $id_segment;

    public $id_job;

    public $source_chunk_lengths;

    public $target_chunk_lengths;

//    public segment_hash               varchar(45) NOT NULL,
//    public autopropagated_from        bigint(20) DEFAULT NULL,
//    public status                     varchar(45) DEFAULT 'NEW',
//    public translation                text,
//    public translation_date           datetime DEFAULT NULL,
//    public time_to_edit               int(11) NOT NULL DEFAULT '0',
//    public match_type                 varchar(45) DEFAULT 'NEW',
//    public context_hash               blob,
//    public eq_word_count              double(20,2) DEFAULT NULL,
//    public standard_word_count        double(20,2) DEFAULT NULL,
//    public suggestions_array          text,
//    public suggestion                 text,
//    public suggestion_match           int(11) DEFAULT NULL,
//    public suggestion_source          varchar(45) DEFAULT NULL,
//    public suggestion_position        int(11) DEFAULT NULL,
//    public mt_qe                      float(19,14) NOT NULL DEFAULT '0.00000000000000',
//    public tm_analysis_status         varchar(50) DEFAULT 'UNDONE',
//    public locked                     tinyint(4) DEFAULT '0',
//    public warning                    tinyint(4) NOT NULL DEFAULT '0',
//    public serialized_errors_list     varchar(512) DEFAULT NULL,

    /**
     * An empty struct
     * @return TranslationsSplit_SplitStruct
     */
    public static function getStruct() {
        return new TranslationsSplit_SplitStruct();
    }

}
