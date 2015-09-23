<?php

class Chunks_ChunkStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {

    public $id;
    public $password;
    public $id_project ;
    public $create_date ;
    public $job_first_segment;
    public $job_last_segment ;
    public $last_opened_segment ;
    public $last_update ;
    public $source ;
    public $target ;
    public $tm_keys ;

    public function getSegments() {
        $dao = new Segments_SegmentDao( Database::obtain() );
        return $dao->getByChunkId( $this->id, $this->password );
    }

    public function isMarkedComplete() {
        return Chunks_ChunkCompletionEventDao::isCompleted( $this ) ;
    }

    public function getTranslations() {
        $dao = new Translations_SegmentTranslationDao( Database::obtain() );
        return $dao->getByJobId( $this->id );
    }

    public function findLatestTranslation() {
        $dao = new Translations_SegmentTranslationDao( Database::obtain() );
        return $dao->lastTranslationByJobOrChunk( $this );
    }

    public function getProject() {
        return $this->getJob()->getProject();
    }

    public function isFeatureEnabled( $feature_code ) {
        return $this->getJob()->isFeatureEnabled( $feature_code );
    }

    public function getJob() {
        return new Jobs_JobStruct( $this->toArray() );
    }

}
