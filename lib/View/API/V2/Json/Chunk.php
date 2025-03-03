<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 21/02/2017
 * Time: 10:41
 */

namespace API\V2\Json;

use Exception;
use Exceptions\NotFoundException;
use Jobs_JobStruct;

class Chunk extends Job {

    /**
     * @param Jobs_JobStruct $chunk
     *
     * @return array
     * @throws Exception
     * @throws NotFoundException
     */
    public function renderOne( Jobs_JobStruct $chunk ) {
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