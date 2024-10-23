<?php

use Translations\WarningDao;

class Chunks_ChunkStruct extends Jobs_JobStruct {

    /** @return Segments_SegmentStruct[]
     *
     */
    public function getSegments(): array {
        $dao = new Segments_SegmentDao( Database::obtain() );

        return $dao->getByChunkId( $this->id, $this->password );
    }

    /**
     * @throws Exception
     */
    public function isMarkedComplete( $params ): bool {
        $params = Utils::ensure_keys( $params, [ 'is_review' ] );

        return Chunks_ChunkCompletionEventDao::isCompleted( $this, [ 'is_review' => $params[ 'is_review' ] ] );
    }

    /**
     * @return Jobs_JobStruct
     */
    public function getJob(): Jobs_JobStruct {
        // I'm doing this to keep the concepts of Chunk and Job as
        // separated as possible even though they share the same
        // database table.
        return new Jobs_JobStruct( $this->toArray() );
    }

    /**
     * @throws ReflectionException
     */
    public function getQualityOverall( array $chunkReviews = [] ): ?string {
        return CatUtils::getQualityOverallFromJobStruct( $this, $chunkReviews );
    }

    public function getErrorsCount(): int {
        $dao = new WarningDao();

        return $dao->getErrorsByChunk( $this );
    }

}
