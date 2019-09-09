<?php


namespace Features ;

use API\V2\Json\ProjectUrls;
use BasicFeatureStruct;
use Features\ReviewExtended\Model\ChunkReviewDao;
use Features\ReviewExtended\View\API\JSON\ProjectUrlsDecorator;
use RevisionFactory;

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

        /**
         * By definition, when running postJobSplitted callback the job is not splitted.
         * So we expect to find just one record in chunk_reviews for the job.
         * If we find more than one record, it's one record for each revision.
         *
         */

        $id_job                     = $projectStructure['job_to_split'];
        $previousRevisionRecords    = ChunkReviewDao::findByIdJob( $id_job );
        $project                    = $previousRevisionRecords[0]->getChunk()->getProject() ;

        $revisionFactory = RevisionFactory::initFromProject($project)
                ->setFeatureSet( $project->getFeatures() ) ;

        ChunkReviewDao::deleteByJobId( $id_job );

        foreach( $previousRevisionRecords as $review ) {
            $this->createQaChunkReviewRecord( $id_job, $projectStructure[ 'id_project' ], [
                    'first_record_password' => $review->review_password,
                    'source_page'           => $review->source_page
            ] );
        }

        $reviews = ChunkReviewDao::findByIdJob( $id_job );
        foreach( $reviews as $review ) {
            $model = $revisionFactory->getChunkReviewModel( $review ) ;
            $model->recountAndUpdatePassFailResult();
        }
    }

    /**
     * @param $projectFeatures
     * @param $controller \NewController|\createProjectController
     *
     * @return mixed
     * @throws \Exception
     */
    public function filterCreateProjectFeatures( $projectFeatures, $controller ) {
        $projectFeatures[ self::FEATURE_CODE ] = new BasicFeatureStruct( [ 'feature_code' => self::FEATURE_CODE ] );
        $projectFeatures                       = $controller->getFeatureSet()->filter( 'filterOverrideReviewExtended', $projectFeatures, $controller );
        return $projectFeatures;
    }

}