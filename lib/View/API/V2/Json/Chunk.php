<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 21/02/2017
 * Time: 10:41
 */

namespace View\API\V2\Json;

use Exception;
use Model\Exceptions\NotFoundException;
use Model\Jobs\JobStruct;

class Chunk extends Job {

    /**
     * @param \Model\Jobs\JobStruct $chunk
     *
     * @return array
     * @throws Exception
     * @throws NotFoundException
     */
    public function renderOne( JobStruct $chunk ): array {
        $project    = $chunk->getProject();
        $featureSet = $project->getFeaturesSet();

        return [
                'job' => [
                        'id'     => (int)$chunk->id,
                        'chunks' => [ $this->renderItem( $chunk, $project, $featureSet ) ]
                ]
        ];
    }

}