<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Filter;

use Model\FeaturesBase\Hook\FilterEvent;
use Model\Projects\ProjectStruct;

/**
 * @see \Controller\API\App\SetTranslationController::evalSetContribution() — dispatch site
 */
final class FilterContributionStructOnSetTranslationEvent extends FilterEvent
{
    public static function hookName(): string
    {
        return 'filterContributionStructOnSetTranslation';
    }

    public function __construct(
        private mixed $contributionStruct,
        private readonly ProjectStruct $project,
        private readonly mixed $segment,
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

    public function getProject(): ProjectStruct
    {
        return $this->project;
    }

    public function getSegment(): mixed
    {
        return $this->segment;
    }
}
