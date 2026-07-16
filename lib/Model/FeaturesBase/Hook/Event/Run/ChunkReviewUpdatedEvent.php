<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Run;

use Model\FeaturesBase\Hook\RunEvent;
use Model\LQA\ChunkReviewStruct;
use Model\Projects\ProjectStruct;

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

    public function __construct(
        public readonly ChunkReviewStruct $chunkReview,
        public readonly mixed $updateResult,
        public readonly mixed $model,
        public readonly ProjectStruct $project,
    ) {
    }
}
