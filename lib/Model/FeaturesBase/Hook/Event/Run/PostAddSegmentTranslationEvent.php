<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Run;

use Model\FeaturesBase\Hook\RunEvent;

/**
 * @see \Controller\API\App\SetTranslationController::persistTranslation() — dispatch site
 */
final class PostAddSegmentTranslationEvent extends RunEvent
{
    public static function hookName(): string
    {
        return 'postAddSegmentTranslation';
    }
    public function __construct(
        public readonly array $context,
    ) {
    }
}
