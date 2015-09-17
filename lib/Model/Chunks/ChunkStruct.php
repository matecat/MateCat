<?php

class Chunks_ChunkStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {

    public $id;
    public $password;
    public $id_project ;
    public $create_date ;
    public $last_opened_segment ;
    public $last_update ;
    public $source ;
    public $target ;
    public $tm_keys ;

    public function getSegments() {
        $dao = new Segments_SegmentDao( Database::obtain() );
        return $dao->getByChunkId( $this->id, $this->password );
    }

    public function getTranslations() {
        $dao = new Translations_SegmentTranslationDao( Database::obtain() );
        return $dao->getByJobId( $this->id );
    }

}
