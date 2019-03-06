<?php


namespace Features ;

use API\V2\Json\ProjectUrls;
use BasicFeatureStruct;
use Features\ReviewExtended\ChunkReviewModel;
use Features\ReviewExtended\Model\ChunkReviewDao;
use Features\ReviewExtended\SegmentTranslationModel;
use Features\ReviewExtended\View\API\JSON\ProjectUrlsDecorator;
use SegmentTranslationChangeVector;

class ReviewExtended extends AbstractRevisionFeature {
    const FEATURE_CODE = 'review_extended' ;

    public static function projectUrls( ProjectUrls $formatted ) {
        $projectUrlsDecorator = new ProjectUrlsDecorator( $formatted->getData() );
        return $projectUrlsDecorator;
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

    public function updateRevisionScore( SegmentTranslationChangeVector $translation ) {
        $model = new SegmentTranslationModel( $translation );
        $model->addOrSubtractCachedReviewedWordsCount();
        // we need to recount score globally because of autopropagation.
        $model->recountPenaltyPoints();
    }

    /**
     * @param $projectFeatures
     * @param $controller \NewController|\createProjectController
     *
     * @return mixed
     */
    public function filterCreateProjectFeatures( $projectFeatures, $controller ) {
        $projectFeatures[ self::FEATURE_CODE ] = new BasicFeatureStruct( [ 'feature_code' => self::FEATURE_CODE ] );
        $controller->getFeatureSet()->filter('filterOverrideReviewExtended', $projectFeatures, $controller );
        return $projectFeatures ;
    }

}