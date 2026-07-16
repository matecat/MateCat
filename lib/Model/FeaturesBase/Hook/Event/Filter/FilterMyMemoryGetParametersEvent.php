<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Filter;

use Model\FeaturesBase\Hook\FilterEvent;

/**
 * @see \Utils\Engines\MyMemory::get() — dispatch site
 */
final class FilterMyMemoryGetParametersEvent extends FilterEvent
{
    public static function hookName(): string
    {
        return 'filterMyMemoryGetParameters';
    }
    /**
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $config
     */
    public function __construct(
        private array $parameters,
        private readonly array $config,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function setParameters(array $parameters): void
    {
        $this->parameters = $parameters;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
