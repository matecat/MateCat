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

    /*
     * These constants refer to the values sent by the APIs and need to be converted
     * into the values that are used internally and to be inserted into the database.
     */
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

    const forValue = [
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
     * Convert API value constants to the internal values
     *
     * @param string $match_type
     *
     * @return string
     */
    public static function toInternalMatchTypeValue( string $match_type ): string {
        switch ( $match_type ) {
            case self::_REPETITIONS:
                return 'REPETITIONS';
            case self::_INTERNAL:
                return 'INTERNAL';
            case self::_50_74:
                return "50%-74%";
            case self::_75_84:
                return "75%-84%";
            case self::_85_94:
                return "85%-94%";
            case self::_95_99:
                return "95%-99%";
            case self::_100:
                return '100%';
            case self::_100_PUBLIC :
                return '100%_PUBLIC';
            case self::_MT:
                return 'MT';
            case self::_ICE:
                return "ICE";
            case "75_99": // no longer used
                return '75%-99%';
            case self::_NEW:
            default:
                return 'NEW';
        }

    }

    /**
     * @param string $name
     *
     * @return string
     * @throws RuntimeException
     */
    public static function validate( $name ): string {

        if ( !array_key_exists( $name, self::forValue ) ) {
            throw new RuntimeException( "Invalid match type: " . $name );
        }

        return $name;

    }

}