<?php

/**
 * Created by PhpStorm.
 * User: roberto <roberto@translated.net>
 * Date: 19/01/15
 * Time: 17.44
 */
class Constants_Revise {

    const NONE  = 'none';
    const MINOR = 'minor';
    const MAJOR = 'major';

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

    public static $const2ServerValues = array(
            self::NONE  => self::SERV_VALUE_NONE,
            self::MINOR => self::SERV_VALUE_MINOR,
            self::MAJOR => self::SERV_VALUE_MAJOR
    );

    public static $categoriesDbNames = [
            'err_typing', 'err_translation', 'err_terminology', 'err_language', 'err_style'
    ];

    const SERV_VALUE_NONE  = 0;
    const SERV_VALUE_MINOR = 0.03;
    const SERV_VALUE_MAJOR = 1;

    const CLIENT_VALUE_NONE  = 0;
    const CLIENT_VALUE_MINOR = 1;
    const CLIENT_VALUE_MAJOR = 2;

    /**
     * Max allowed errors for category
     */
    const WORD_INTERVAL    = 1000;
    const MAX_TYPING       = 2;
    const MAX_TRANSLATION  = 2;
    const MAX_TERMINOLOGY  = 3;
    const MAX_QUALITY      = 3;
    const MAX_STYLE        = 5;

    const ERR_TYPING       = 'Typing';
    const ERR_TRANSLATION  = 'Translation';
    const ERR_TERMINOLOGY  = 'Terminology';
    const ERR_LANGUAGE     = 'Language Quality';
    const ERR_STYLE        = 'Style';

    const VOTE_EXCELLENT  = "Excellent";
    const VOTE_VERY_GOOD  = "Very Good";
    const VOTE_GOOD       = "Good";
    const VOTE_ACCEPTABLE = "Acceptable";
    const VOTE_POOR       = "Poor";
    const VOTE_FAIL       = "Fail";

    public static $equivalentScoreMap = array(

            '80' => 0.10,
            '75' => 0.22,
            '70' => 0.34,
            '65' => 0.46,
            '60' => 0.58,
            '55' => 0.70,
            '50' => 0.82,
            '45' => 0.94,
            '40' => 1.06,
            '35' => 1.18,
            '30' => 9999,

    );

} 