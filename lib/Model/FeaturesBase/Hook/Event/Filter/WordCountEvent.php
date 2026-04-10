<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Filter;

use Model\FeaturesBase\Hook\FilterEvent;

/**
 * @see \Model\ProjectCreation\SegmentExtractor::processSegSourceTransUnit() — dispatch site
 */
final class WordCountEvent extends FilterEvent
{
    public static function hookName(): string
    {
        return 'wordCount';
    }

    public function __construct(
        private mixed $wordCount,
    ) {
    }

    public function getWordCount(): mixed
    {
        return $this->wordCount;
    }

    public function setWordCount(mixed $wordCount): void
    {
        $this->wordCount = $wordCount;
    }
}
