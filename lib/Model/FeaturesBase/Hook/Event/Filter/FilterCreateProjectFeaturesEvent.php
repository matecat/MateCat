<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Filter;

use Model\FeaturesBase\Hook\FilterEvent;

/**
 * @see \Controller\API\V1\NewController::appendFeaturesToProject() — dispatch site
 * @see \Controller\API\App\CreateProjectController::appendFeaturesToProject() — dispatch site
 */
final class FilterCreateProjectFeaturesEvent extends FilterEvent
{
    public static function hookName(): string
    {
        return 'filterCreateProjectFeatures';
    }
    /**
     * @param array<int|string, mixed> $projectFeatures
     */
    public function __construct(
        private array $projectFeatures,
        private readonly mixed $controller = null,
    ) {
    }
    /**
     * @return array<int|string, mixed>
     */
    public function getProjectFeatures(): array
    {
        return $this->projectFeatures;
    }
    /**
     * @param array<int|string, mixed> $projectFeatures
     */
    public function setProjectFeatures(array $projectFeatures): void
    {
        $this->projectFeatures = $projectFeatures;
    }

    public function getController(): mixed
    {
        return $this->controller;
    }
}
