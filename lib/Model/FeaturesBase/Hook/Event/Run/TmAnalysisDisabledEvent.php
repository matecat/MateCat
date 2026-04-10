<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Run;

use Model\FeaturesBase\Hook\RunEvent;

/**
 * @see \Utils\AsyncTasks\Workers\Analysis\FastAnalysis::main() — dispatch site
 */
final class TmAnalysisDisabledEvent extends RunEvent
{
    public static function hookName(): string
    {
        return 'tmAnalysisDisabled';
    }

    public function __construct(
        public readonly int $projectId,
    ) {
    }
}
