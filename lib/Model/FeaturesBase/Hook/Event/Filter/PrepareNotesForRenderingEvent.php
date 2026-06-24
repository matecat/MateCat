<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Filter;

use Model\FeaturesBase\Hook\FilterEvent;

/**
 * @see \Controller\API\App\GetSegmentsController::attachNotes() — dispatch site
 */
final class PrepareNotesForRenderingEvent extends FilterEvent
{
    public static function hookName(): string
    {
        return 'prepareNotesForRendering';
    }
    /**
     * @param array<int|string, mixed> $notes
     */
    public function __construct(
        private array $notes,
    ) {
    }
    /**
     * @return array<int|string, mixed>
     */
    public function getNotes(): array
    {
        return $this->notes;
    }
    /**
     * @param array<int|string, mixed> $notes
     */
    public function setNotes(array $notes): void
    {
        $this->notes = $notes;
    }
}
