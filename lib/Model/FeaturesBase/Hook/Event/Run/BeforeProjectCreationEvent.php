<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Run;

use Model\FeaturesBase\Hook\RunEvent;
use Model\ProjectCreation\ProjectStructure;

/**
 * @see \Model\ProjectCreation\ProjectManager::extractSegmentsCreateProjectAndStoreData() — dispatch site
 */
final class BeforeProjectCreationEvent extends RunEvent
{
    public static function hookName(): string
    {
        return 'beforeProjectCreation';
    }
    public function __construct(
        public readonly ProjectStructure $projectStructure,
        public readonly array $context,
    ) {
    }
}
