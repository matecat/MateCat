<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Filter;

use Model\FeaturesBase\Hook\FilterEvent;

/**
 * @see \Model\ProjectCreation\ProjectManager::insertInstructions() — dispatch site
 * @see \Controller\API\V3\FileInfoController::setInstructions() — dispatch site
 */
final class DecodeInstructionsEvent extends FilterEvent
{
    public static function hookName(): string
    {
        return 'decodeInstructions';
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
