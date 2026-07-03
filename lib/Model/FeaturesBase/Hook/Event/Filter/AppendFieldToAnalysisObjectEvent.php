<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Filter;

use Model\FeaturesBase\Hook\FilterEvent;
use Model\ProjectCreation\ProjectStructure;

/**
 * @see \Model\ProjectCreation\SegmentStorageService::prepareAndPersistSegment() — dispatch site
 */
final class AppendFieldToAnalysisObjectEvent extends FilterEvent
{
    public static function hookName(): string
    {
        return 'appendFieldToAnalysisObject';
    }
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        private array $metadata,
        private readonly ProjectStructure $projectStructure,
    ) {
    }
    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
    /**
     * @param array<string, mixed> $metadata
     */
    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function getProjectStructure(): ProjectStructure
    {
        return $this->projectStructure;
    }
}
