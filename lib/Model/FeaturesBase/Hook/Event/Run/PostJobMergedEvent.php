<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Run;

use Model\FeaturesBase\Hook\RunEvent;
use Model\Jobs\JobStruct;
use Model\JobSplitMerge\SplitMergeProjectData;
use Model\Users\UserStruct;

/**
 * @see \Model\JobSplitMerge\JobSplitMergeService::mergeALL() — dispatch site
 */
final class PostJobMergedEvent extends RunEvent
{
    public static function hookName(): string
    {
        return 'postJobMerged';
    }

    /**
     * @param UserStruct $actingUser Who ran the merge. See PostJobSplittedEvent for why this is not
     *                               taken from SplitMergeProjectData::$uid.
     */
    public function __construct(
        public readonly SplitMergeProjectData $data,
        public readonly JobStruct $chunk,
        public readonly UserStruct $actingUser,
    ) {
    }
}
