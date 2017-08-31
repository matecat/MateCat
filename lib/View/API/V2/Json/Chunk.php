<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 21/02/2017
 * Time: 10:41
 */

namespace API\V2\Json;
use Routes ;

class Chunk {

    public static function renderItem( \Chunks_ChunkStruct $chunk ) {

        $project = $chunk->getProject();

        $urls = [
                'translate' => Routes::translate(
                            $project->name,
                            $chunk->id ,
                            $chunk->password,
                            $chunk->source ,
                            $chunk->target
                    ),
                ];

        if ( !$chunk->getProject()-> isFeatureEnabled(\Features::REVIEW_IMPROVED) ) {
            $urls['revise'] = Routes::revise(
                    $project->name,
                    $chunk->id,
                    $chunk->password,
                    $chunk->source,
                    $chunk->target
            );
        }

        return [
            'status'             => $chunk->status_owner,
            'password'           => $chunk->password,
            'created_at'         => \Utils::api_timestamp($chunk->create_date),
            'open_threads_count' => (int) $chunk->getOpenThreadsCount(),
            'urls'               => $urls,
            'quality_summary'    => [
                    'equivalent_class' => $chunk->getQualityInfo(),
                    'quality_overall'  => $chunk->getQualityOverall(),
                    'errors_count'     => (int) $chunk->getErrorsCount()
            ],
            'pee'                => $chunk->getPeeForTranslatedSegments()
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