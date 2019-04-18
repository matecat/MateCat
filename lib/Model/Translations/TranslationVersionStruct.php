<?php

class Translations_TranslationVersionStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {

    public $id ;
    public $id_segment ;
    public $id_job ;
    public $translation ;
    public $creation_date ;
    public $version_number ;
    public $propagated_from ;
    public $time_to_edit ;

    public $is_review ;
    public $raw_diff ;

    public $old_status ;
    public $new_status ;
}
