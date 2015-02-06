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

    const NONE  = 'none';
    const MINOR = 'minor';
    const MAJOR = 'major';

    const CLIENT_VALUE_NONE  = 0;
    const CLIENT_VALUE_MINOR = 1;
    const CLIENT_VALUE_MAJOR = 2;

    /**
     * Max allowed errors for category
     */
    const WORD_INTERVAL    = 2500;
    const MAX_TYPING       = 5;
    const MAX_TRANSLATION  = 2;
    const MAX_TERMINOLOGY  = 3;
    const MAX_QUALITY      = 4;
    const MAX_STYLE        = 5;

    const ERR_TYPING       = 'Typing';
    const ERR_TRANSLATION  = 'Translation';
    const ERR_TERMINOLOGY  = 'Terminology';
    const ERR_QUALITY      = 'Language Quality';
    const ERR_STYLE        = 'Style';

    const VOTE_EXCELLENT  = "Excellent";
    const VOTE_VERY_GOOD  = "Very Good";
    const VOTE_GOOD       = "Good";
    const VOTE_ACCEPTABLE = "Acceptable";
    const VOTE_POOR       = "Poor";
    const VOTE_FAIL       = "Fail";

} 