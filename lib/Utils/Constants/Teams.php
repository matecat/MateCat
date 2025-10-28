<?php

namespace Utils\Constants;
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 02/02/17
 * Time: 14.47
 *
 */
class Teams {

    const string PERSONAL = 'personal';
    const string GENERAL  = 'general';

    protected static array $TYPES = [
            self::PERSONAL,
            self::GENERAL
    ];


    public static function isAllowedType( $type ): bool {
        return in_array( strtolower( $type ), self::$TYPES );
    }

}