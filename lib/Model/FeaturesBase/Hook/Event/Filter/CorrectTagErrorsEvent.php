<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Filter;

use Model\FeaturesBase\Hook\FilterEvent;

/**
 * @see \Model\ProjectCreation\SegmentStorageService::prepareAndPersistSegment() — dispatch site
 */
final class CorrectTagErrorsEvent extends FilterEvent
{
    public static function hookName(): string
    {
        return 'correctTagErrors';
    }
    public function __construct(
        private string $segment,
        /** @var array<string, mixed> */
        private readonly array $originalDataMap,
    ) {
    }

    public function getSegment(): string
    {
        return $this->segment;
    }

    public function setSegment(string $segment): void
    {
        $this->segment = $segment;
    }
    /**
     * @return array<string, mixed>
     */
    public function getOriginalDataMap(): array
    {
        return $this->originalDataMap;
    }
}
