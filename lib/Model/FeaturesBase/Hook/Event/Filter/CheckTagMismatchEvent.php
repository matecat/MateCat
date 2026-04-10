<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Filter;

use Model\FeaturesBase\Hook\FilterEvent;

/**
 * @see \Utils\LQA\QA\TagChecker::checkTagCountMismatch() — dispatch site
 */
final class CheckTagMismatchEvent extends FilterEvent
{
    public static function hookName(): string
    {
        return 'checkTagMismatch';
    }

    public function __construct(
        private int $errorCode,
        private readonly mixed $qaInstance,
    ) {
    }

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    public function setErrorCode(int $errorCode): void
    {
        $this->errorCode = $errorCode;
    }

    public function getQaInstance(): mixed
    {
        return $this->qaInstance;
    }
}
