<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Filter;

use Model\FeaturesBase\Hook\FilterEvent;

/**
 * @see \Controller\Abstracts\BaseKleinViewController::setView() — dispatch site
 * @see \Controller\API\Commons\Validators\InternalUserValidator::_validate() — dispatch site
 * @see \Controller\API\Commons\Validators\IsOwnerInternalUserValidator::_validate() — dispatch site
 */
final class IsAnInternalUserEvent extends FilterEvent
{
    private bool $isInternal = false;

    public static function hookName(): string
    {
        return 'isAnInternalUser';
    }

    public function __construct(
        private readonly string $email,
    ) {
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function isInternal(): bool
    {
        return $this->isInternal;
    }

    public function setIsInternal(bool $isInternal): void
    {
        $this->isInternal = $isInternal;
    }
}
