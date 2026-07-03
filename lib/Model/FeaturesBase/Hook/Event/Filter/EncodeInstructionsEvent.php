<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Filter;

use Model\FeaturesBase\Hook\FilterEvent;

/**
 * @see \Controller\API\V1\NewController::validateTheRequest() — dispatch site
 */
final class EncodeInstructionsEvent extends FilterEvent
{
    public static function hookName(): string
    {
        return 'encodeInstructions';
    }

    public function __construct(
        private mixed $value,
    ) {
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function setValue(mixed $value): void
    {
        $this->value = $value;
    }
}
