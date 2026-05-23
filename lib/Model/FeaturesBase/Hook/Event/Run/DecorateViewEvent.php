<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Run;

use Model\FeaturesBase\Hook\RunEvent;
use PHPTAL;

final class DecorateViewEvent extends RunEvent
{
    public static function hookName(): string
    {
        return 'decorateView';
    }

    public function __construct(
        public readonly PHPTAL $view,
        public readonly string $templateName,
        public readonly string $nonce,
    ) {
    }
}
