<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Run;

use Model\FeaturesBase\Hook\RunEvent;
use Model\JobSplitMerge\SplitMergeProjectData;

/**
 * @see \Model\JobSplitMerge\JobSplitMergeService::splitJob() — dispatch site
 */
final class PostJobSplittedEvent extends RunEvent
{
    public static function hookName(): string
    {
        return 'postJobSplitted';
    }

    public function __construct(
        public readonly SplitMergeProjectData $data,
    ) {
    }
}
