<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Run;

use Model\FeaturesBase\Hook\RunEvent;

/**
 * @see \Controller\API\V2\ChangeProjectNameController::changeName() — dispatch site
 */
final class FilterProjectNameModifiedEvent extends RunEvent
{
    public static function hookName(): string
    {
        return 'filterProjectNameModified';
    }

    public function __construct(
        public readonly int $idProject,
        public readonly string $name,
        public readonly string $password,
        public readonly string $ownerEmail,
    ) {
    }
}
