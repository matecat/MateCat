<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Run;

use Model\FeaturesBase\Hook\RunEvent;

/**
 * @see \Controller\API\V2\ChangePasswordController::changeThePassword() — dispatch site
 */
final class ReviewPasswordChangedEvent extends RunEvent
{
    public static function hookName(): string
    {
        return 'reviewPasswordChanged';
    }

    public function __construct(
        public readonly int $jobId,
        public readonly string $oldPassword,
        public readonly string $newPassword,
        public readonly int $revisionNumber,
    ) {
    }
}
