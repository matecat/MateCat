<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Run;

use Model\FeaturesBase\Hook\RunEvent;
use Model\Jobs\JobStruct;
use Model\JobSplitMerge\SplitMergeProjectData;

/**
 * @see \Model\JobSplitMerge\JobSplitMergeService::mergeALL() — dispatch site
 */
final class PostJobMergedEvent extends RunEvent
{
    public static function hookName(): string
    {
        return 'postJobMerged';
    }

    public function __construct(
        public readonly SplitMergeProjectData $data,
        public readonly JobStruct $chunk,
    ) {
    }
}
