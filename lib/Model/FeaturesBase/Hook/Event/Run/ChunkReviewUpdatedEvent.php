<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Run;

use Model\FeaturesBase\Hook\RunEvent;
use Model\LQA\ChunkReviewStruct;
use Model\Projects\ProjectStruct;
use Model\Users\UserStruct;

/**
 * Dispatched after the commit of the transaction that updated the chunk review, once per row, with
 * the committed row.
 *
 * @see \Plugins\Features\ReviewExtended\ChunkReviewModel::publishUpdated() — dispatch site
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
