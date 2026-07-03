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
    /**
     * @param array<string, mixed> $rates
     */
    public function __construct(
        private array $rates,
        private readonly string $sourceLanguage,
        private readonly string $targetLanguage,
    ) {
    }
    /**
     * @return array<string, mixed>
     */
    public function getRates(): array
    {
        return $this->rates;
    }
    /**
     * @param array<string, mixed> $rates
     */
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
