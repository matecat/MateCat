<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Filter;

use Model\ActivityLog\ActivityLogStruct;
use Model\FeaturesBase\Hook\FilterEvent;

/**
 * @see \View\API\V2\Json\Activity::render() — dispatch site
 */
final class FilterActivityLogEntryEvent extends FilterEvent
{
    public static function hookName(): string
    {
        return 'filterActivityLogEntry';
    }

    public function __construct(
        private ActivityLogStruct $record,
    ) {
    }

    public function getRecord(): ActivityLogStruct
    {
        return $this->record;
    }

    public function setRecord(ActivityLogStruct $record): void
    {
        $this->record = $record;
    }
}
