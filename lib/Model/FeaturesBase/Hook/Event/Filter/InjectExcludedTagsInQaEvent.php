<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Filter;

use Model\FeaturesBase\Hook\FilterEvent;

/**
 * @see \Utils\LQA\QA\DomHandler::addThisElementToDomMap() — dispatch site
 */
final class InjectExcludedTagsInQaEvent extends FilterEvent
{
    public static function hookName(): string
    {
        return 'injectExcludedTagsInQa';
    }
    public function __construct(
        private array $excludedTags,
    ) {
    }
    public function getExcludedTags(): array
    {
        return $this->excludedTags;
    }
    public function setExcludedTags(array $excludedTags): void
    {
        $this->excludedTags = $excludedTags;
    }
}
