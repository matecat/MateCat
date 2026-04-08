<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Run;

use Model\FeaturesBase\Hook\RunEvent;
use Model\Jobs\JobStruct;
use Model\ProjectCreation\ProjectStructure;

/**
 * @see \Model\ProjectCreation\JobCreationService::createJobsForTargetLanguages() — dispatch site
 */
final class ValidateJobCreationEvent extends RunEvent
{
    public static function hookName(): string
    {
        return 'validateJobCreation';
    }

    public function __construct(
        public readonly JobStruct $job,
        public readonly ProjectStructure $projectStructure,
    ) {
    }
}
