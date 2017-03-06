<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 02/02/17
 * Time: 14.47
 *
 */


class Constants_Teams {

    const PERSONAL = 'personal';
    const GENERAL  = 'general';

    protected static $TYPES = [
            self::PERSONAL,
            self::GENERAL
    ];


    public static function isAllowedType( $type ){
        return in_array( strtolower( $type ), self::$TYPES );
    }

}