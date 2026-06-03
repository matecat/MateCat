<?php

namespace Controller\Abstracts\Authentication;

/**
 * Created by PhpStorm.
 * @author ostico domenico@translated.net / ostico@gmail.com
 * Date: 19/04/22
 * Time: 18:41
 *
 */
class CookieManager
{

    /**
     * @param array<string, mixed> $options
     */
    public static function setCookie(string $name, string $value = "", array $options = []): bool
    {
        if (headers_sent()) {
            return false;
        }

        return setcookie($name, $value, $options);
    }

}