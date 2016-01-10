<?php

class Chunks_ChunkStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {

    public $id;
    public $password;
    public $id_project ;
    public $create_date ;
    public $job_first_segment;
    public $job_last_segment ;
    public $last_opened_segment ;
    public $owner ;
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

    /**
     * getProject
     *
     * Returns the project struct, caching the result on the instance to avoid
     * unnecessary queries.
     *
     * @return \Projects_ProjectStruct
     */

    public function getProject() {
        return $this->cachable(__function__, $this->getJob(),  function( $job ) {
            return $job->getProject();
        });
    }

    public function isFeatureEnabled( $feature_code ) {
        return $this->getJob()->isFeatureEnabled( $feature_code );
    }

    /**
     * @return Jobs_JobStruct
     */
    public function getJob() {
        // I'm doing this to keep the concepts of Chunk and Job as
        // separated as possible even though they share the same
        // database table.
        return new Jobs_JobStruct( $this->toArray() );
    }

}
