<?php

namespace Controller\Abstracts;

use Exception;


class FlashMessage
{

    const string KEY = 'flashMessages';

    const string WARNING = 'warning';
    const string ERROR = 'error';
    const string INFO = 'info';
    const string SERVICE = 'service';

    /**
     * @throws Exception
     */
    public static function set($key, $value, $type = self::WARNING)
    {
        if (!isset($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = [
                self::WARNING => [],
                self::ERROR => [],
                self::INFO => []
            ];
        }

        $_SESSION[self::KEY] [$type] [] = [
            'key' => $key,
            'value' => $value
        ];
    }

    public static function flush()
    {
        $out = null;
        if (isset($_SESSION[self::KEY])) {
            $out = $_SESSION[self::KEY];
            unset($_SESSION[self::KEY]);
        }

        return $out;
    }

}