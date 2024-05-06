<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 14/11/23
 * Time: 12:06
 *
 */

namespace API\App\Json\Analysis;

use RuntimeException;

class MatchConstants {

    const _NEW          = "new";
    const _50_74        = "50_74";
    const _75_84        = "75_84";
    const _85_94        = "85_94";
    const _95_99        = "95_99";
    const _100          = "100";
    const _100_PUBLIC   = "100_public";
    const _ICE          = "ice";
    const _MT           = "MT";
    const _REPETITIONS  = 'repetitions';
    const _INTERNAL     = 'internal';
    const _NUMBERS_ONLY = 'numbers_only';

    public static $forValue = [
            self::_NEW          => self::_NEW,
            self::_50_74        => self::_50_74,
            self::_75_84        => self::_75_84,
            self::_85_94        => self::_85_94,
            self::_95_99        => self::_95_99,
            self::_100          => self::_100,
            self::_100_PUBLIC   => self::_100_PUBLIC,
            self::_ICE          => self::_ICE,
            self::_MT           => self::_MT,
            self::_REPETITIONS  => self::_REPETITIONS,
            self::_INTERNAL     => self::_INTERNAL,
            self::_NUMBERS_ONLY => self::_NUMBERS_ONLY,
    ];

    /**
     * @param string $name
     *
     * @return string
     * @throws RuntimeException
     */
    public static function validate( $name ) {

        if ( !array_key_exists( $name, self::$forValue ) ) {
            throw new RuntimeException( "Invalid match type: " . $name );
        }

        return $name;

    }

}