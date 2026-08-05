<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Run;

use Model\FeaturesBase\Hook\RunEvent;
use Model\JobSplitMerge\SplitMergeProjectData;
use Model\Users\UserStruct;

/**
 * @see \Model\JobSplitMerge\JobSplitMergeService::splitJob() — dispatch site
 */
final class PostJobSplittedEvent extends RunEvent
{
    public static function hookName(): string
    {
        return 'postJobSplitted';
    }

    /**
     * @param UserStruct $actingUser Who ran the split. Threaded explicitly rather than read from
     *                               SplitMergeProjectData::$uid, which is declared but never
     *                               assigned and additionally gates translator re-invitation.
     */
    public function __construct(
        public readonly SplitMergeProjectData $data,
        public readonly UserStruct $actingUser,
    ) {
    }
}
