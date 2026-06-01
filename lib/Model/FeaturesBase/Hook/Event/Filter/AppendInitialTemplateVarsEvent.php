<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Filter;

use Model\FeaturesBase\Hook\FilterEvent;

/**
 * @see \Controller\Views\AnalyzeController::renderView() — dispatch site
 * @see \Controller\Views\CattoolController::renderView() — dispatch site
 */
final class AppendInitialTemplateVarsEvent extends FilterEvent
{
    public static function hookName(): string
    {
        return 'appendInitialTemplateVars';
    }
    /** @param array<int|string, mixed> $codes */
    public function __construct(
        private array $codes,
    ) {
    }
    /** @return array<int|string, mixed> */
    public function getCodes(): array
    {
        return $this->codes;
    }
    /** @param array<int|string, mixed> $codes */
    public function setCodes(array $codes): void
    {
        $this->codes = $codes;
    }
}
