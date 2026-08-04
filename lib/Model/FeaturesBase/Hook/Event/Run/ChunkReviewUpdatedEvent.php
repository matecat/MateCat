<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Run;

use Model\FeaturesBase\Hook\RunEvent;
use Model\LQA\ChunkReviewStruct;
use Model\Projects\ProjectStruct;
use Model\Users\UserStruct;

/**
 * @see \Plugins\Features\ReviewExtended\ChunkReviewModel::_updatePassFailResult() — dispatch site
 * @see \Plugins\Features\ReviewExtended\ChunkReviewModel::recountAndUpdatePassFailResult() — dispatch site
 */
final class ChunkReviewUpdatedEvent extends RunEvent
{
    public static function hookName(): string
    {
        return 'chunkReviewUpdated';
    }

    /**
     * @param UserStruct $actingUser Who caused the update. Required, and deliberately without a
     *                               default: every dispatch site is reached from an authenticated
     *                               request or from a worker carrying the user that enqueued it, so
     *                               an absent actor is a wiring bug, not a runtime state. Listeners
     *                               previously had to read it out of $_SESSION, which yielded 0 on
     *                               every worker-driven update.
     */
    public function __construct(
        public readonly ChunkReviewStruct $chunkReview,
        public readonly mixed $updateResult,
        public readonly mixed $model,
        public readonly ProjectStruct $project,
        public readonly UserStruct $actingUser,
    ) {
    }
}
