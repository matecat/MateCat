<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Run;

use Model\ChunksCompletion\ChunkCompletionEventStruct;
use Model\FeaturesBase\Hook\RunEvent;

/**
 * @see \Controller\API\App\CompletionEventController::__performUndo() — dispatch site
 */
final class AlterChunkReviewStructEvent extends RunEvent
{
    public static function hookName(): string
    {
        return 'alterChunkReviewStruct';
    }

    public function __construct(
        public readonly ChunkCompletionEventStruct $event,
    ) {
    }
}
