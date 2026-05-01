<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Filter;

use Model\FeaturesBase\Hook\FilterEvent;

/**
 * @see \Model\Analysis\AbstractStatus::isOutsourceEnabled() — dispatch site
 * @see \View\API\V2\Json\Job::renderItem() — dispatch site
 */
final class OutsourceAvailableInfoEvent extends FilterEvent
{
    public static function hookName(): string
    {
        return 'outsourceAvailableInfo';
    }

    public function __construct(
        private mixed $filterable,
        private readonly string $idCustomer,
        private readonly int $idJob,
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

    public function getIdCustomer(): string
    {
        return $this->idCustomer;
    }

    public function getIdJob(): int
    {
        return $this->idJob;
    }
}
