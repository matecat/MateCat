<?php

class Chunks_ChunkStruct extends Jobs_JobStruct {

    /** @return Segments_SegmentStruct[]
     *
     */
    public function getSegments() {
        $dao = new Segments_SegmentDao( Database::obtain() );

        return $dao->getByChunkId( $this->id, $this->password );
    }

    public function isMarkedComplete( $params ) {
        $params = \Utils::ensure_keys( $params, array( 'is_review' ) );

        return Chunks_ChunkCompletionEventDao::isCompleted( $this, array( 'is_review' => $params[ 'is_review' ] ) );
    }

    /**
     * @return Translations_SegmentTranslationStruct[]
     */
    public function getTranslations() {
        $dao = new Translations_SegmentTranslationDao( Database::obtain() );

        return $dao->getByJobId( $this->id );
    }

    /**
     * @return Jobs_JobStruct
     */
    public function getJob() {
        // I'm doing this to keep the concepts of Chunk and Job as
        // separated as possible even though they share the same
        // database table.
        return new Jobs_JobStruct( $this->attributes() );
    }

    public function getQualityOverall() {
        return CatUtils::getQualityOverallFromJobStruct( $this ) ;
    }

    public function getQualityInfo(){
        $qClass = CatUtils::getQualityInfoFromJobStruct( $this );

        if ( 'LQA\ChunkReviewStruct' === get_class( $qClass ) ) {
            return null ;
        }
        else {
            return ( isset( $qClass[ 'equivalent_class' ] ) ? $qClass[ 'equivalent_class' ] : null );
        }
    }

    public function getErrorsCount() {
        $dao = new \Translations\WarningDao() ;
        return $dao->getErrorsByChunk( $this );
    }
}
