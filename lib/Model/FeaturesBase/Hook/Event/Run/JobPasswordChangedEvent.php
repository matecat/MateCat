<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Run;

use Model\FeaturesBase\Hook\RunEvent;
use Model\Jobs\JobStruct;

/**
 * @see \Model\Translators\TranslatorsModel::changeJobPassword() — dispatch site
 * @see \Controller\API\V2\ChangePasswordController::changeThePassword() — dispatch site
 */
final class JobPasswordChangedEvent extends RunEvent
{
    public static function hookName(): string
    {
        return 'jobPasswordChanged';
    }

    public function __construct(
        public readonly JobStruct $job,
        public readonly string $oldPassword,
    ) {
    }
}
