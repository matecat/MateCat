<?php

class Translations_TranslationVersionStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {

    public $id ;
    public $id_segment ;
    public $id_job ;
    public $translation ;
    public $creation_date ;
    public $version_number ;
    public $propagated_from ;
}
