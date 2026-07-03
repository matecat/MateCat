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
    public static function set(string $key, string $value, string $type = self::WARNING): void
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

    /**
     * @return array<string, array<int, array{key: string, value: string}>>|null
     */
    public static function flush(): ?array
    {
        $out = null;
        if (isset($_SESSION[self::KEY])) {
            $out = $_SESSION[self::KEY];
            unset($_SESSION[self::KEY]);
        }

        return $out;
    }

}