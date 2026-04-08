<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Filter;

use Model\FeaturesBase\Hook\FilterEvent;

/**
 * @see \Plugins\Features\ReviewExtended\ReviewedWordCountModel::_sendNotificationEmail() — dispatch site
 */
final class FilterRevisionChangeNotificationListEvent extends FilterEvent
{
    public static function hookName(): string
    {
        return 'filterRevisionChangeNotificationList';
    }
    public function __construct(
        private array $emails,
    ) {
    }
    public function getEmails(): array
    {
        return $this->emails;
    }
    public function setEmails(array $emails): void
    {
        $this->emails = $emails;
    }
}
