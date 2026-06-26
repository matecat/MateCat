<?php

namespace Plugins\Features;

use Exception;
use Model\ChunksCompletion\ChunkCompletionEventDao;
use Model\ChunksCompletion\ChunkCompletionUpdateDao;
use Model\ChunksCompletion\ChunkCompletionUpdateStruct;
use Model\FeaturesBase\BasicFeatureStruct;
use Model\FeaturesBase\Hook\Event\Run\JobPasswordChangedEvent;
use Model\FeaturesBase\Hook\Event\Run\PostAddSegmentTranslationEvent;
use Model\Jobs\JobStruct;
use RuntimeException;
use TypeError;
use Utils\Tools\Utils;

class ProjectCompletion extends BaseFeature
{

    const string FEATURE_CODE = 'project_completion';

    private ?ChunkCompletionEventDao $chunkCompletionEventDao;
    private ?ChunkCompletionUpdateDao $chunkCompletionUpdateDao;

    public function __construct(
        BasicFeatureStruct $feature,
        ?ChunkCompletionEventDao $chunkCompletionEventDao = null,
        ?ChunkCompletionUpdateDao $chunkCompletionUpdateDao = null,
    ) {
        parent::__construct($feature);
        // DAOs are optional: the FeatureSet framework instantiates features with only the
        // feature struct, so they default to null and are built lazily on first use (below).
        $this->chunkCompletionEventDao = $chunkCompletionEventDao;
        $this->chunkCompletionUpdateDao = $chunkCompletionUpdateDao;
    }

    // Lazy accessors: build from the database injected via setDatabase() (called by the
    // framework after construction), never falling back to Database::obtain().
    private function chunkCompletionEventDao(): ChunkCompletionEventDao
    {
        return $this->chunkCompletionEventDao ??= new ChunkCompletionEventDao($this->getDatabase());
    }

    private function chunkCompletionUpdateDao(): ChunkCompletionUpdateDao
    {
        return $this->chunkCompletionUpdateDao ??= new ChunkCompletionUpdateDao($this->getDatabase());
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    public function postAddSegmentTranslation(PostAddSegmentTranslationEvent $event): void
    {
        $params = $event->context;
        $params = Utils::ensure_keys($params, ['is_review', 'chunk']);

        // Here we need to find or update the corresponding record,
        // to register the event of the segment translation being updated
        // from a "review" page or a "translate" page.

        /** @var JobStruct $chunk */
        $chunk = $params['chunk'];
        $chunk_completion_update_struct = new ChunkCompletionUpdateStruct($chunk->toArray());
        $chunk_completion_update_struct->is_review = $params['is_review'];
        $chunk_completion_update_struct->source = 'user';
        $chunk_completion_update_struct->id_job = $chunk->id ?? throw new RuntimeException('Job id is required');

        if (isset($params['logged_user']) && $params['logged_user']->uid) {
            $chunk_completion_update_struct->uid = $params['logged_user']->uid;
        }

        $chunk_completion_update_struct->setTimestamp('last_translation_at', time());

        $current_phase = $this->chunkCompletionEventDao()->currentPhase($chunk);

        /**
         * Only save the record if the current phase is compatible
         */
        if (
            ($current_phase == ChunkCompletionEventDao::REVISE && $chunk_completion_update_struct->is_review) ||
            ($current_phase == ChunkCompletionEventDao::TRANSLATE && !$chunk_completion_update_struct->is_review)
        ) {
            $this->chunkCompletionUpdateDao()->createOrUpdateFromStruct($chunk_completion_update_struct);
        }
    }

    /**
     * @throws RuntimeException
     */
    public function jobPasswordChanged(JobPasswordChangedEvent $event): void
    {
        $idJob = $event->job->id ?? throw new RuntimeException('Job id is required when updating completion passwords');
        $password = $event->job->password ?? throw new RuntimeException('Job password is required when updating completion passwords');

        $this->chunkCompletionUpdateDao()->updatePassword($idJob, $password, $event->oldPassword);
        $this->chunkCompletionEventDao()->updatePassword($idJob, $password, $event->oldPassword);
    }

}
