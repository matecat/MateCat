<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/05/2017
 * Time: 12:01
 */

namespace Plugins\Features\ProjectCompletion\Model;

use Controller\Features\ProjectCompletion\CompletionEventStruct;
use Exception;
use Model\ChunksCompletion\ChunkCompletionEventDao;
use Model\FeaturesBase\FeatureSet;
use Model\FeaturesBase\Hook\Event\Run\ProjectCompletionEventSavedEvent;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectDao;
use ReflectionException;
use TypeError;


class EventModel
{

    /**
     * @var CompletionEventStruct
     */
    protected CompletionEventStruct $eventStruct;
    /**
     * @var JobStruct
     */
    protected JobStruct $chunk;
    protected ?int $chunkCompletionEventId = null;

    private ChunkCompletionEventDao $chunkCompletionEventDao;
    private ProjectDao $projectDao;
    private FeatureSet $featureSet;

    /**
     * @throws Exception
     */
    public function __construct(
        JobStruct $chunk,
        CompletionEventStruct $eventStruct,
        ChunkCompletionEventDao $chunkCompletionEventDao,
        ProjectDao $projectDao,
        ?FeatureSet $featureSet = null,
    ) {
        $this->eventStruct = $eventStruct;
        $this->chunk = $chunk;
        $this->chunkCompletionEventDao = $chunkCompletionEventDao;
        $this->projectDao = $projectDao;
        $this->featureSet = $featureSet ?? new FeatureSet();
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    public function save(): void
    {
        $this->_checkStatusIsValid();

        $this->chunkCompletionEventId = (int)$this->chunkCompletionEventDao->createFromChunk(
            $this->chunk,
            $this->eventStruct
        );

        $project = $this->projectDao->findById($this->chunk->id_project) ?? throw new Exception('Project not found for chunk ' . $this->chunk->id_project);
        $this->featureSet->loadForProject($project);
        $this->featureSet->dispatch(new ProjectCompletionEventSavedEvent($this->chunk, $this->eventStruct, (int)$this->chunkCompletionEventId));
    }

    public function getChunkCompletionEventId(): ?int
    {
        return $this->chunkCompletionEventId;
    }

    /**
     * @throws Exception
     */
    private function _checkStatusIsValid(): void
    {
        $current_phase = $this->chunkCompletionEventDao->currentPhase($this->chunk);

        if (
            ($this->eventStruct->is_review && $current_phase != ChunkCompletionEventDao::REVISE) ||
            (!$this->eventStruct->is_review && $current_phase != ChunkCompletionEventDao::TRANSLATE)
        ) {
            throw new Exception('Cannot save event, current status mismatch.');
        }
    }
}
