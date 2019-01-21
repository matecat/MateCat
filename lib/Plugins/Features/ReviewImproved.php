<?php

namespace Features ;

use Features\ReviewImproved\ChunkReviewModel;
use Features\ReviewImproved\SegmentTranslationModel;
use LQA\ChunkReviewDao;
use LQA\ChunkReviewStruct;
use SegmentTranslationChangeVector;

class ReviewImproved extends AbstractRevisionFeature {
    const FEATURE_CODE = 'review_improved' ;

    protected static $conflictingDependencies = [
            ReviewExtended::FEATURE_CODE
    ];


    /**
     * postJobSplitted
     *
     * Deletes the previously created record and creates the new records matching the new chunks.
     *
     * @param \ArrayObject $projectStructure
     *
     * @throws \Exceptions\ValidationError
     */
    public function postJobSplitted( \ArrayObject $projectStructure ) {

        $id_job = $projectStructure['job_to_split'];
        $old_reviews = ChunkReviewDao::findByIdJob( $id_job );
        $first_password = $old_reviews[0]->review_password ;

        ChunkReviewDao::deleteByJobId( $id_job );

        $this->createQaChunkReviewRecord( $id_job, $projectStructure[ 'id_project' ], [
                'first_record_password' => $first_password
        ] );

        $reviews = ChunkReviewDao::findByIdJob( $id_job );
        foreach( $reviews as $review ) {
            //if($projectStructure->)
            $model = new ChunkReviewModel($review);
            $model->recountAndUpdatePassFailResult();
        }
    }

    public function getChunkReviewModel(ChunkReviewStruct $chunk_review) {
        return new ChunkReviewModel( $chunk_review );
    }

    /**
     * @param SegmentTranslationChangeVector $translation
     *
     * @return ISegmentTranslationModel
     */
    public function getSegmentTranslationModel( SegmentTranslationChangeVector $translation ) {
        return new SegmentTranslationModel( $translation );
    }

}
