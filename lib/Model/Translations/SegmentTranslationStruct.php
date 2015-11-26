<?php

class Translations_SegmentTranslationStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {

    public $id_segment ;
    public $id_job ;
    public $segment_hash ;
    public $autopropagated_from ;
    public $status ;
    public $translation ;
    public $translation_date ;
    public $time_to_edit ;
    public $match_type ;
    public $context_hash ;
    public $eq_word_count ;
    public $standard_word_count ;
    public $suggestions_array ;
    public $suggestion ;
    public $suggestion_match ;
    public $suggestion_source ;
    public $suggestion_position ;
    public $mt_qe ;
    public $tm_analysis_status ;
    public $locked ;
    public $warning ;
    public $serialized_errors_list ;
    public $version_number ;

}
