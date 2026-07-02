<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Filter;

use Model\FeaturesBase\Hook\FilterEvent;

/**
 * @see \Model\ProjectCreation\SegmentExtractor::detectPreTranslation() — dispatch site
 */
final class PopulatePreTranslationsEvent extends FilterEvent
{
    public static function hookName(): string
    {
        return 'populatePreTranslations';
    }

    public function __construct(
        private bool $default,
    ) {
    }

    public function getDefault(): bool
    {
        return $this->default;
    }

    public function setDefault(bool $default): void
    {
        $this->default = $default;
    }
}
