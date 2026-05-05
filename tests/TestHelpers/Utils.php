<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 15/01/25
 * Time: 12:45
 *
 */

namespace TestHelpers;

class Utils
{

    public static function array_is_list(array $arr): bool
    {
        // from php 8.1
        if (!function_exists('array_is_list')) {
            function array_is_list(array $arr): bool
            {
                if ($arr === []) {
                    return true;
                }

                return array_keys($arr) === range(0, count($arr) - 1);
            }
        }

        // from php 8.1
        return array_is_list($arr);
    }

}