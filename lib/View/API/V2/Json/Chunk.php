<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 21/02/2017
 * Time: 10:41
 */

namespace API\V2\Json;
use Features\ReviewImproved;
use Routes ;

class Chunk {

    public static function renderItem( \Chunks_ChunkStruct $chunk ) {

        $project = $chunk->getProject();

        return [
            'password'           => $chunk->password,
            'created_at'         => \Utils::api_timestamp($chunk->create_date),
            'open_threads_count' => (int) $chunk->getOpenThreadsCount(),

            'urls' => [
                    'translate' => Routes::translate(
                            $project->name,
                            $chunk->id ,
                            $chunk->password,
                            $chunk->source ,
                            $chunk->target
                    ),
                    'revise' => Routes::revise(
                            $project->name,
                            $chunk->id,
                            ReviewImproved\Utils::revisePassword( $chunk ),
                            $chunk->source,
                            $chunk->target
                    )
            ],

            'quality_summary'    => [
                    'quality_overall'  => $chunk->getQualityOverall(),
                    'errors_count'     => (int) $chunk->getErrorsCount()
            ]
        ];
    }

    public static function renderOne( \Chunks_ChunkStruct $chunk ) {
        return [
                'job' => [
                        'id' => (int) $chunk->id,
                        'chunks' =>
                                [
                                        self::renderItem( $chunk )
                                ]
                ]
        ] ;
    }

}