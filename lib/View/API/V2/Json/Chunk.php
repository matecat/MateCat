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
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;

class Chunk extends Job
{

    /**
     * @param JobStruct $chunk
     *
     * @return array<string, mixed>
     * @throws Exception
     * @throws NotFoundException
     * @throws \TypeError
     */
    public function renderOne(JobStruct $chunk): array
    {
        $project = $chunk->getProject();
        $featureSet = FeatureSet::forProject($project, $this->database);

        return [
            'job' => [
                'id' => (int)$chunk->id,
                'chunks' => [$this->renderItem($chunk, $project, $featureSet)]
            ]
        ];
    }

}