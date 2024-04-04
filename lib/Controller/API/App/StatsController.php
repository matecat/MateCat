<?php

namespace API\App;

use API\V2\KleinController;
use API\V2\Validators\ChunkPasswordValidator;
use CatUtils;
use Chunks_ChunkStruct;
use Features\ReviewExtended\ReviewUtils;
use LQA\ChunkReviewDao;
use Projects_MetadataDao;
use WordCount\WordCountStruct;

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
    protected $chunk;

    public function setChunk( Chunks_ChunkStruct $chunk ) {
        $this->chunk = $chunk;
    }

    /**
     * @return void
     */
    public function stats() {

        $wStruct = WordCountStruct::loadFromJob( $this->chunk );

        // YYY [Remove] backward compatibility for current projects
        $counterType = $this->chunk->getProject( 60 * 60 )->getWordCountType();
        $job_stats   = CatUtils::getFastStatsForJob( $wStruct, true, $counterType );
        if ( $counterType == Projects_MetadataDao::WORD_COUNT_EQUIVALENT ) {
            $chunk_reviews        = ( new ChunkReviewDao() )->findChunkReviews( $this->chunk );
            $job_stats = [ 'stats' => ReviewUtils::formatStats( $job_stats, $chunk_reviews ) ];
            $job_stats[ 'stats' ][ 'analysis_complete' ] = $this->chunk->getProject()->analysisComplete();
        } else {
            $job_stats[ 'analysis_complete' ] = $this->chunk->getProject()->analysisComplete();
        }

        $this->response->json( $job_stats );
    }

    protected function afterConstruct() {
        $Validator  = ( new ChunkPasswordValidator( $this ) );
        $Controller = $this;
        $Validator->onSuccess( function () use ( $Validator, $Controller ) {
            $Controller->setChunk( $Validator->getChunk() );
        } );

        $this->appendValidator( $Validator );
    }

}
