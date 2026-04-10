<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Run;

use Model\FeaturesBase\Hook\RunEvent;

/**
 * @see \Controller\API\App\SetTranslationController::buildResult() — dispatch site
 * @see \Controller\API\App\GetSearchController::updateSegments() — dispatch site
 */
final class SetTranslationCommittedEvent extends RunEvent
{
    public static function hookName(): string
    {
        return 'setTranslationCommitted';
    }
    public function __construct(
        public readonly array $context,
    ) {
    }
}
