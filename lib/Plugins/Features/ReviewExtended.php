<?php


namespace Features ;

use API\V2\Json\ProjectUrls;
use Features\ReviewExtended\Model\ChunkReviewDao;
use Features\ReviewExtended\View\API\JSON\ProjectUrlsDecorator;
use Features\ReviewExtended\Observer\SegmentTranslationObserver;
use SegmentTranslationModel;
use Features\ReviewExtended\ChunkReviewModel;

class ReviewExtended extends AbstractRevisionFeature {
    const FEATURE_CODE = 'review_extended' ;

    protected static $conflictingDependencies = [
        ReviewImproved::FEATURE_CODE
    ];

    public static function projectUrls( ProjectUrls $formatted ) {
        $projectUrlsDecorator = new ProjectUrlsDecorator( $formatted->getData() );
        return $projectUrlsDecorator;
    }

    protected function attachObserver( SegmentTranslationModel $translation_model ){
        /**
         * This implementation may seem overkill since we are already into review improved feature
         * so we could avoid to delegate to an observer. This is done with aim to the future when
         * the SegmentTranslationModel will be used directly into setTranslation controller.
         */
        $translation_model->attach( new SegmentTranslationObserver() );
    }

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
            $model = new ChunkReviewModel($review);
            $model->recountAndUpdatePassFailResult();
        }

    }

}