<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Filter;

use Model\FeaturesBase\Hook\FilterEvent;

/**
 * @see \Model\ProjectCreation\JobCreationService::resolvePayableRates() — dispatch site
 */
final class FilterPayableRatesEvent extends FilterEvent
{
    public static function hookName(): string
    {
        return 'filterPayableRates';
    }
    public function __construct(
        private array $rates,
        private readonly string $sourceLanguage,
        private readonly string $targetLanguage,
    ) {
    }
    public function getRates(): array
    {
        return $this->rates;
    }
    public function setRates(array $rates): void
    {
        $this->rates = $rates;
    }

    public function getSourceLanguage(): string
    {
        return $this->sourceLanguage;
    }

    public function getTargetLanguage(): string
    {
        return $this->targetLanguage;
    }
}
