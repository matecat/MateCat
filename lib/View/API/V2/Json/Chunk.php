<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 21/02/2017
 * Time: 10:41
 */

namespace API\V2\Json;

class Chunk extends Job {

    /**
     * @param \Chunks_ChunkStruct $chunk
     *
     * @return array
     * @throws \Exception
     * @throws \Exceptions\NotFoundException
     */
    public function renderOne( \Chunks_ChunkStruct $chunk ) {
        $project = $chunk->getProject();
        $featureSet = $project->getFeaturesSet();
        return [
                'job' => [
                        'id'     => (int)$chunk->id,
                        'chunks' => [ $this->renderItem( $chunk, $project, $featureSet ) ]
                ]
        ];
    }

}