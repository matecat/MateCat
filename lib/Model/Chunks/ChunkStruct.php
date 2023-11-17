<?php

use Translations\WarningDao;

class Chunks_ChunkStruct extends Jobs_JobStruct {

    /** @return Segments_SegmentStruct[]
     *
     */
    public function getSegments() {
        $dao = new Segments_SegmentDao( Database::obtain() );

        return $dao->getByChunkId( $this->id, $this->password );
    }

    public function isMarkedComplete( $params ) {
        $params = Utils::ensure_keys( $params, array( 'is_review' ) );

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
     * @throws ReflectionException
     */
    public function getJob() {
        // I'm doing this to keep the concepts of Chunk and Job as
        // separated as possible even though they share the same
        // database table.
        return new Jobs_JobStruct( $this->toArray() );
    }

    public function getQualityOverall(array $chunkReviews = []) {
        return CatUtils::getQualityOverallFromJobStruct( $this, $chunkReviews ) ;
    }

    public function getErrorsCount() {
        $dao = new WarningDao() ;
        return $dao->getErrorsByChunk( $this );
    }

}
