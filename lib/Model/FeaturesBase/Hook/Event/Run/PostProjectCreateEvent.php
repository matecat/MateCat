<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Run;

use Model\FeaturesBase\Hook\RunEvent;
use Model\ProjectCreation\ProjectStructure;

/**
 * @see \Model\ProjectCreation\ProjectManager::finalizeProjectInTransaction() — dispatch site
 */
final class PostProjectCreateEvent extends RunEvent
{
    public static function hookName(): string
    {
        return 'postProjectCreate';
    }

    public function __construct(
        public readonly ProjectStructure $projectStructure,
    ) {
    }
}
