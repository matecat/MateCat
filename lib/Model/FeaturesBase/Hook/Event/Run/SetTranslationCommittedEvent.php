<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Run;

use Model\FeaturesBase\Hook\RunEvent;
use Model\Users\UserStruct;

/**
 * @see \Controller\API\App\SetTranslationController::buildResult() — dispatch site
 * @see \Controller\API\App\GetSearchController::updateSegments() — dispatch site
 */
final class SetTranslationCommittedEvent extends RunEvent
{
    public static function hookName(): string
    {
        return 'setTranslationCommitted';
    }
    /**
     * @param array<string, mixed> $context
     * @param UserStruct $actingUser Who committed the translation. A typed field rather than another
     *                               $context key so listeners get the actor without narrowing mixed,
     *                               and cannot silently fall back to request-global state.
     */
    public function __construct(
        public readonly array $context,
        public readonly UserStruct $actingUser,
    ) {
    }
}
