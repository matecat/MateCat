<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook;

abstract class FilterEvent
{
    abstract public static function hookName(): string;
}
