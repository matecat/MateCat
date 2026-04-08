<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook;

abstract class RunEvent
{
    abstract public static function hookName(): string;
}
