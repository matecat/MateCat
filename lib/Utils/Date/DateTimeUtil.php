<?php

namespace Date;

use DateTime;

class DateTimeUtil {

    /**
     * @param null $date
     * @return string
     * @throws \Exception
     */
    public static function formatIsoDate($date = null)
    {
        if($date !== null){
            $date = new DateTime($date);

            return $date->format(DateTime::ATOM);
        }
    }
}