<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 21/02/2017
 * Time: 10:41
 */

namespace API\V2\Json;
use LQA\ChunkReviewDao;
use Routes ;

class Chunk extends Job {

    /**
     * @param \Chunks_ChunkStruct $chunk
     *
     * @return array
     * @throws \Exception
     * @throws \Exceptions\NotFoundError
     */
    public function renderItem( \Chunks_ChunkStruct $chunk ) {

        $result = parent::renderItem( $chunk );

        $project = $chunk->getProject();

        $result[ 'urls' ][ 'translate' ] = Routes::translate(
                $project->name,
                $chunk->id,
                $chunk->password,
                $chunk->source,
                $chunk->target
        );

        if ( !$chunk->getProject()-> isFeatureEnabled( \Features::REVIEW_IMPROVED ) ) {

            $reviewChunk = ChunkReviewDao::findOneChunkReviewByIdJobAndPassword(
                    $chunk->id, $chunk->password
            );

            $result[ 'urls' ][ 'revise' ] = Routes::revise(
                    $project->name,
                    $chunk->id,
                    ( !empty( $reviewChunk ) ? $reviewChunk->review_password : $chunk->password ),
                    $chunk->source,
                    $chunk->target
            );

        }

        $result[ 'quality_summary' ] = [
                'equivalent_class' => $chunk->getQualityInfo(),
                'quality_overall'  => $chunk->getQualityOverall(),
                'errors_count'     => (int)$chunk->getErrorsCount()
        ];

        return $result;
    }

    /**
     * @param \Chunks_ChunkStruct $chunk
     *
     * @return array
     * @throws \Exception
     * @throws \Exceptions\NotFoundError
     */
    public function renderOne( \Chunks_ChunkStruct $chunk ) {
        return [
                'job' => [
                        'id'     => (int)$chunk->id,
                        'chunks' => [ $this->renderItem( $chunk ) ]
                ]
        ];
    }

}