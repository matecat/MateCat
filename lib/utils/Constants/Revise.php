<?php

/**
 * Created by PhpStorm.
 * User: roberto <roberto@translated.net>
 * Date: 19/01/15
 * Time: 17.44
 */
class Constants_Revise {

    public static $ERR_TYPES_MAP = array(
            self::CLIENT_VALUE_NONE  => self::NONE,
            self::CLIENT_VALUE_MINOR => self::MINOR,
            self::CLIENT_VALUE_MAJOR => self::MAJOR,
    );

    public static $const2clientValues = array(
            self::NONE  => self::CLIENT_VALUE_NONE,
            self::MINOR => self::CLIENT_VALUE_MINOR,
            self::MAJOR => self::CLIENT_VALUE_MAJOR
    );

    const NONE = 'none';
    const MINOR = 'minor';
    const MAJOR = 'major';

    const CLIENT_VALUE_NONE = 0;
    const CLIENT_VALUE_MINOR = 1;
    const CLIENT_VALUE_MAJOR = 2;

} 