<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Filter;

use Model\FeaturesBase\Hook\FilterEvent;

/**
 * @see \Utils\LQA\QA\TagChecker::checkTagPositions() — dispatch site
 */
final class CheckTagPositionsEvent extends FilterEvent
{
    public static function hookName(): string
    {
        return 'checkTagPositions';
    }

    public function __construct(
        private bool $errorCode,
        private readonly mixed $qaInstance,
    ) {
    }

    public function getErrorCode(): bool
    {
        return $this->errorCode;
    }

    public function setErrorCode(bool $errorCode): void
    {
        $this->errorCode = $errorCode;
    }

    public function getQaInstance(): mixed
    {
        return $this->qaInstance;
    }
}
