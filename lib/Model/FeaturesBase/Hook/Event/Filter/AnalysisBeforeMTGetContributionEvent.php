<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Filter;

use Model\FeaturesBase\Hook\FilterEvent;

/**
 * @see \Utils\AsyncTasks\Workers\Analysis\TMAnalysisWorker::_getMT() — dispatch site
 */
final class AnalysisBeforeMTGetContributionEvent extends FilterEvent
{
    public static function hookName(): string
    {
        return 'analysisBeforeMTGetContribution';
    }
    public function __construct(
        private array $config,
        private readonly mixed $mtEngine,
        private readonly mixed $queueElement,
    ) {
    }
    public function getConfig(): array
    {
        return $this->config;
    }
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function getMtEngine(): mixed
    {
        return $this->mtEngine;
    }

    public function getQueueElement(): mixed
    {
        return $this->queueElement;
    }
}
