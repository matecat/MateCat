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
use Model\DataAccess\IDatabase;
use Model\DataAccess\TransactionalTrait;
use Model\FeaturesBase\FeatureSet;
use Model\FeaturesBase\Hook\Event\Run\ProjectCompletionEventSavedEvent;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectDao;
use ReflectionException;
use Throwable;
use TypeError;


class EventModel
{

    use TransactionalTrait;

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
    private IDatabase $database;

    /**
     * @throws Exception
     */
    public function __construct(
        JobStruct $chunk,
        CompletionEventStruct $eventStruct,
        ChunkCompletionEventDao $chunkCompletionEventDao,
        ProjectDao $projectDao,
        FeatureSet $featureSet,
        IDatabase $database,
    ) {
        $this->eventStruct = $eventStruct;
        $this->chunk = $chunk;
        $this->chunkCompletionEventDao = $chunkCompletionEventDao;
        $this->projectDao = $projectDao;
        $this->featureSet = $featureSet;
        $this->database = $database;
    }

    protected function getTransactionalDatabase(): IDatabase
    {
        return $this->database;
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    public function save(): void
    {
        $this->_checkStatusIsValid();

        // The event row and the listeners it dispatches are one unit. ProjectCompletionEventSaved
        // reaches AbstractRevisionFeature::projectCompletionEventSaved() -> QualityReportModel::
        // resetScore(), a read-modify-write that takes the job's qa_chunk_reviews row locks and
        // refuses to run outside a transaction — so without this the whole endpoint threw.
        //
        // It is also correct independently of the lock: resetScore() snapshots the pre-reset
        // counters into undo_data, and that snapshot and this event row must not be able to
        // survive each other. CompletionEventController::__performUndo() already does the same for
        // the undo direction.
        $this->openTransaction();

        try {
            $this->chunkCompletionEventId = (int)$this->chunkCompletionEventDao->createFromChunk(
                $this->chunk,
                $this->eventStruct
            );

            $project = $this->projectDao->findById($this->chunk->id_project) ?? throw new Exception('Project not found for chunk ' . $this->chunk->id_project);
            $this->featureSet->loadForProject($project);
            $this->featureSet->dispatch(new ProjectCompletionEventSavedEvent($this->chunk, $this->eventStruct, (int)$this->chunkCompletionEventId));

            $this->commitTransaction();
        } catch (Throwable $e) {
            $this->rollbackTransaction();
            throw $e;
        }
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
