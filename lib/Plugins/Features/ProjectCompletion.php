<?php

namespace Plugins\Features;

use Exception;
use Model\ChunksCompletion\ChunkCompletionEventDao;
use Model\ChunksCompletion\ChunkCompletionUpdateDao;
use Model\ChunksCompletion\ChunkCompletionUpdateStruct;
use Model\FeaturesBase\Hook\Event\Run\JobPasswordChangedEvent;
use Model\FeaturesBase\Hook\Event\Run\PostAddSegmentTranslationEvent;
use Model\Jobs\JobStruct;
use RuntimeException;
use Utils\Tools\Utils;

class ProjectCompletion extends BaseFeature
{

    const string FEATURE_CODE = 'project_completion';

    /**
     * @throws Exception
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
        $chunk_completion_update_struct->id_job = $chunk->id;

        if (isset($params['logged_user']) && $params['logged_user']->uid) {
            $chunk_completion_update_struct->uid = $params['logged_user']->uid;
        }

        $chunk_completion_update_struct->setTimestamp('last_translation_at', time());

        $dao = new ChunkCompletionEventDao();
        $current_phase = $dao->currentPhase($chunk);

        /**
         * Only save the record if the current phase is compatible
         */
        if (
            ($current_phase == ChunkCompletionEventDao::REVISE && $chunk_completion_update_struct->is_review) ||
            ($current_phase == ChunkCompletionEventDao::TRANSLATE && !$chunk_completion_update_struct->is_review)
        ) {
            ChunkCompletionUpdateDao::createOrUpdateFromStruct($chunk_completion_update_struct);
        }
    }

    /**
     * @throws RuntimeException
     */
    public function jobPasswordChanged(JobPasswordChangedEvent $event): void
    {
        $idJob = $event->job->id ?? throw new RuntimeException('Job id is required when updating completion passwords');
        $password = $event->job->password ?? throw new RuntimeException('Job password is required when updating completion passwords');

        $dao = new ChunkCompletionUpdateDao();
        $dao->updatePassword($idJob, $password, $event->oldPassword);

        $dao = new ChunkCompletionEventDao();
        $dao->updatePassword($idJob, $password, $event->oldPassword);
    }

}
