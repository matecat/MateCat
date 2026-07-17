<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Filter;

use Model\FeaturesBase\Hook\FilterEvent;

/**
 * @see \Controller\API\V2\UrlsController::urls() — dispatch site
 * @see \View\API\V2\Json\Job::fillUrls() — dispatch site
 */
final class ProjectUrlsEvent extends FilterEvent
{
    public static function hookName(): string
    {
        return 'projectUrls';
    }

    public function __construct(
        private mixed $formatted,
    ) {
    }

    public function getFormatted(): mixed
    {
        return $this->formatted;
    }

    public function setFormatted(mixed $formatted): void
    {
        $this->formatted = $formatted;
    }
}
