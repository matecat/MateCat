<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Run;

use Controller\Features\ProjectCompletion\CompletionEventStruct;
use Model\FeaturesBase\Hook\RunEvent;
use Model\Jobs\JobStruct;

/**
 * @see \Plugins\Features\ProjectCompletion\Model\EventModel::save() — dispatch site
 */
final class ProjectCompletionEventSavedEvent extends RunEvent
{
    public static function hookName(): string
    {
        return 'projectCompletionEventSaved';
    }

    public function __construct(
        public readonly JobStruct $chunk,
        public readonly CompletionEventStruct $event,
        public readonly int $completionEventId,
    ) {
    }
}
