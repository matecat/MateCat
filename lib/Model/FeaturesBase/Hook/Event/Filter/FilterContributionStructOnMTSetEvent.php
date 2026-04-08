<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Filter;

use Model\FeaturesBase\Hook\FilterEvent;

/**
 * @see \Controller\API\App\SetTranslationController::evalSetContribution() — dispatch site
 */
final class FilterContributionStructOnMTSetEvent extends FilterEvent
{
    public static function hookName(): string
    {
        return 'filterContributionStructOnMTSet';
    }

    public function __construct(
        private mixed $contributionStruct,
        private readonly mixed $translation,
        private readonly mixed $segment,
        private readonly mixed $filter,
    ) {
    }

    public function getContributionStruct(): mixed
    {
        return $this->contributionStruct;
    }

    public function setContributionStruct(mixed $contributionStruct): void
    {
        $this->contributionStruct = $contributionStruct;
    }

    public function getTranslation(): mixed
    {
        return $this->translation;
    }

    public function getSegment(): mixed
    {
        return $this->segment;
    }

    public function getFilter(): mixed
    {
        return $this->filter;
    }
}
