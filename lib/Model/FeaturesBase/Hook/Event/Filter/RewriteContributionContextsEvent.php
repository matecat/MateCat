<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Filter;

use Model\FeaturesBase\Hook\FilterEvent;

/**
 * @see \Controller\API\App\SetTranslationController::getContexts() — dispatch site
 * @see \Controller\API\App\GetContributionController::rewriteContributionContexts() — dispatch site
 */
final class RewriteContributionContextsEvent extends FilterEvent
{
    public static function hookName(): string
    {
        return 'rewriteContributionContexts';
    }
    /**
     * @param array<string, mixed> $requestData
     */
    public function __construct(
        private mixed $segmentsList,
        private readonly array $requestData,
    ) {
    }

    public function getSegmentsList(): mixed
    {
        return $this->segmentsList;
    }

    public function setSegmentsList(mixed $segmentsList): void
    {
        $this->segmentsList = $segmentsList;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRequestData(): array
    {
        return $this->requestData;
    }
}
