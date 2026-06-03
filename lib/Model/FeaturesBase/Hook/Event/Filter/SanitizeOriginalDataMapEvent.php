<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Filter;

use Model\FeaturesBase\Hook\FilterEvent;

/**
 * @see \Model\ProjectCreation\SegmentStorageService::prepareAndPersistSegment() — dispatch site
 */
final class SanitizeOriginalDataMapEvent extends FilterEvent
{
    public static function hookName(): string
    {
        return 'sanitizeOriginalDataMap';
    }
    /**
     * @param array<string, mixed> $originalDataMap
     */
    public function __construct(
        private array $originalDataMap,
    ) {
    }
    /**
     * @return array<string, mixed>
     */
    public function getOriginalDataMap(): array
    {
        return $this->originalDataMap;
    }
    /**
     * @param array<string, mixed> $originalDataMap
     */
    public function setOriginalDataMap(array $originalDataMap): void
    {
        $this->originalDataMap = $originalDataMap;
    }
}
