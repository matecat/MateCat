<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 02/02/17
 * Time: 14.47
 *
 */


class Constants_Organizations {

    const PERSONAL = 'personal';

    protected static $TYPES = [
            self::PERSONAL
    ];


    public static function isAllowedType( $type ){
        return in_array( strtolower( $type ), self::$TYPES );
    }

}