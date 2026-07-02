<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Filter;

use Model\FeaturesBase\Hook\FilterEvent;

/**
 * @see \Utils\LQA\SizeRestriction\SizeRestriction::getCleanedStringLength() — dispatch site
 */
final class CharacterLengthCountEvent extends FilterEvent
{
    public static function hookName(): string
    {
        return 'characterLengthCount';
    }

    public function __construct(
        private mixed $filterable,
    ) {
    }

    public function getFilterable(): mixed
    {
        return $this->filterable;
    }

    public function setFilterable(mixed $filterable): void
    {
        $this->filterable = $filterable;
    }
}
