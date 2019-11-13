<?php

namespace API\App;

use API\V2\KleinController;
use API\V2\Validators\ChunkPasswordValidator;
use CatUtils;
use Chunks_ChunkStruct;
use Features\SecondPassReview;
use Features\ReviewExtended\ReviewUtils;
use FeatureSet;
use LQA\ChunkReviewDao;
use WordCount_Struct;

/**
 * Class StatsController
 *
 * This class was copied from API\V1 instead of extended so to favour a complete refactoring.
 *
 * @package API\App
 */
class StatsController extends KleinController {

    /**
     * @var Chunks_ChunkStruct
     */
    protected $chunk ;

    public function setChunk( Chunks_ChunkStruct $chunk ){
        $this->chunk = $chunk;
    }

    /**
     * @throws \API\V2\Exceptions\AuthenticationError
     * @throws \Exceptions\NotFoundException
     * @throws \Exceptions\ValidationError
     * @throws \TaskRunner\Exceptions\EndQueueException
     * @throws \TaskRunner\Exceptions\ReQueueException
     */
    public function stats() {

        $wStruct = new WordCount_Struct();

        $wStruct->setIdJob( $this->chunk->id );
        $wStruct->setJobPassword( $this->chunk->password );
        $wStruct->setNewWords( $this->chunk->new_words );
        $wStruct->setDraftWords( $this->chunk->draft_words );
        $wStruct->setTranslatedWords( $this->chunk->translated_words );
        $wStruct->setApprovedWords( $this->chunk->approved_words );
        $wStruct->setRejectedWords( $this->chunk->rejected_words );

        $job_stats = CatUtils::getFastStatsForJob( $wStruct );
        $job_stats['ANALYSIS_COMPLETE'] = $this->chunk->getProject()->analysisComplete() ;

        $chunk_reviews = ( new ChunkReviewDao() )->findChunkReviews( $this->chunk ) ;

        $response = [
            'stats' => ReviewUtils::formatStats( $job_stats, $chunk_reviews )
        ];

        $this->featureSet = new FeatureSet();
        $this->featureSet->loadForProject( $this->chunk->getProject( 60 * 60 ) )  ;
        $response = $this->featureSet->filter('filterStatsControllerResponse', $response, [ 'chunk' => $this->chunk ] );
        $this->response->json( $response ) ;
    }

    protected function afterConstruct() {
        $Validator = ( new ChunkPasswordValidator( $this ) );
        $Controller = $this;
        $Validator->onSuccess( function () use ( $Validator, $Controller ) {
            $Controller->setChunk( $Validator->getChunk() );
        } );

        $this->appendValidator( $Validator );
    }

}
