<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Filter;

use Model\FeaturesBase\Hook\FilterEvent;

/**
 * @see \Plugins\Features\ProjectCompletion\Model\ProjectCompletionStatusModel::populateStatus() — dispatch site
 */
final class FilterJobPasswordToReviewPasswordEvent extends FilterEvent
{
    public static function hookName(): string
    {
        return 'filterJobPasswordToReviewPassword';
    }

    public function __construct(
        private string $password,
        private readonly int $idJob,
    ) {
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function getIdJob(): int
    {
        return $this->idJob;
    }
}
