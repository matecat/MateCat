<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Run;

use Model\FeaturesBase\Hook\RunEvent;
use Model\ProjectCreation\ProjectStructure;

/**
 * @see \Model\ProjectCreation\ProjectManager::validateBeforeCreation() — dispatch site
 */
final class ValidateProjectCreationEvent extends RunEvent
{
    public static function hookName(): string
    {
        return 'validateProjectCreation';
    }

    public function __construct(
        public readonly ProjectStructure $projectStructure,
    ) {
    }
}
