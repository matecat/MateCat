<?php

namespace Utils\Date;

use DateTime;
use DateTimeInterface;
use Exception;

class DateTimeUtil
{

    /**
     * @param null $date
     *
     * @return ?string
     * @throws Exception
     */
    public static function formatIsoDate($date = null): ?string
    {
        if ($date !== null) {
            $date = new DateTime($date);

            return $date->format(DateTimeInterface::ATOM);
        }

        return null;
    }
}