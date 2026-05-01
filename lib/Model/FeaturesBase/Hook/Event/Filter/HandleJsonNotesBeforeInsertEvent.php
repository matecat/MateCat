<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Filter;

use Model\FeaturesBase\Hook\FilterEvent;
use Model\ProjectCreation\ProjectStructure;

/**
 * @see \Model\ProjectCreation\ProjectManager::insertSegmentNotesForFile() — dispatch site
 */
final class HandleJsonNotesBeforeInsertEvent extends FilterEvent
{
    public static function hookName(): string
    {
        return 'handleJsonNotesBeforeInsert';
    }

    public function __construct(
        private ProjectStructure $projectStructure,
    ) {
    }

    public function getProjectStructure(): ProjectStructure
    {
        return $this->projectStructure;
    }

    public function setProjectStructure(ProjectStructure $projectStructure): void
    {
        $this->projectStructure = $projectStructure;
    }
}
