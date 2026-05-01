<?php

namespace Utils\Date;

use DateTime;
use DateTimeInterface;
use Exception;

class DateTimeUtil
{

    /**
     * @param ?string $date
     *
     * @return ?string
     * @throws Exception
     */
    public static function formatIsoDate(?string $date = null): ?string
    {
        if ($date !== null) {
            $date = new DateTime($date);

            return $date->format(DateTimeInterface::ATOM);
        }

        return null;
    }
}