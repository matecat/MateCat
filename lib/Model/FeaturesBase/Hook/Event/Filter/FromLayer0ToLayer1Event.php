<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Filter;

use Matecat\SubFiltering\Commons\Pipeline;
use Model\FeaturesBase\Hook\FilterEvent;

final class FromLayer0ToLayer1Event extends FilterEvent
{
    public static function hookName(): string
    {
        return 'fromLayer0ToLayer1';
    }

    public function __construct(
        private Pipeline $channel,
    ) {
    }

    public function getChannel(): Pipeline
    {
        return $this->channel;
    }

    public function setChannel(Pipeline $channel): void
    {
        $this->channel = $channel;
    }
}
