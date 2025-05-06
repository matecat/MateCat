<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 14/11/23
 * Time: 12:06
 *
 */

namespace Model\Analysis\Constants;

class StandardMatchConstants extends AbstractConstants {

    protected static string $workflow_type = 'standard';

    public static function getWorkflowType(): string {
        return static::$workflow_type;
    }

    /*
     * These constants refer to the values sent by the APIs and need to be converted
     * into the values that are used internally and to be inserted into the database.
     */
    const _NEW          = "new";
    const _50_74        = "tm_50_74";
    const _75_84        = "tm_75_84";
    const _85_94        = "tm_85_94";
    const _95_99        = "tm_95_99";
    const _100          = "tm_100";
    const _100_PUBLIC   = "tm_100_public";
    const _ICE          = "ice";
    const _MT           = "MT";
    const _ICE_MT       = "ice_mt";
    const _REPETITIONS  = 'repetitions';
    const _INTERNAL     = 'internal';
    const _NUMBERS_ONLY = 'numbers_only';

    protected static array $forValue = [
            self::_NEW          => self::_NEW,
            self::_50_74        => self::_50_74,
            self::_75_84        => self::_75_84,
            self::_85_94        => self::_85_94,
            self::_95_99        => self::_95_99,
            self::_100          => self::_100,
            self::_100_PUBLIC   => self::_100_PUBLIC,
            self::_ICE          => self::_ICE,
            self::_MT           => self::_MT,
            self::_ICE_MT       => self::_ICE_MT,
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
        $mapping = [
                self::_REPETITIONS  => InternalMatchesConstants::REPETITIONS,
                self::_INTERNAL     => InternalMatchesConstants::INTERNAL,
                self::_50_74        => InternalMatchesConstants::TM_50_74,
                self::_75_84        => InternalMatchesConstants::TM_75_84,
                self::_85_94        => InternalMatchesConstants::TM_85_94,
                self::_95_99        => InternalMatchesConstants::TM_95_99,
                self::_100          => InternalMatchesConstants::TM_100,
                self::_100_PUBLIC   => InternalMatchesConstants::TM_100_PUBLIC,
                self::_MT           => InternalMatchesConstants::MT,
                self::_ICE_MT       => InternalMatchesConstants::ICE_MT,
                self::_ICE          => InternalMatchesConstants::TM_ICE,
                self::_NUMBERS_ONLY => InternalMatchesConstants::NUMBERS_ONLY,
                self::_NEW          => InternalMatchesConstants::NEW,
        ];

        return $mapping[ $match_type ] ?? strtoupper( $match_type );
    }

    public static function toExternalMatchTypeValue( string $match_type ): string {
        $mapping = [
                InternalMatchesConstants::REPETITIONS   => self::_REPETITIONS,
                InternalMatchesConstants::INTERNAL      => self::_INTERNAL,
                InternalMatchesConstants::TM_50_74      => self::_50_74,
                InternalMatchesConstants::TM_75_84      => self::_75_84,
                InternalMatchesConstants::TM_85_94      => self::_85_94,
                InternalMatchesConstants::TM_95_99      => self::_95_99,
                InternalMatchesConstants::TM_100        => self::_100,
                InternalMatchesConstants::TM_100_PUBLIC => self::_100_PUBLIC,
                InternalMatchesConstants::MT            => self::_MT,
                InternalMatchesConstants::ICE_MT        => self::_ICE_MT,
                InternalMatchesConstants::TM_ICE        => self::_ICE,
                InternalMatchesConstants::NUMBERS_ONLY  => self::_NUMBERS_ONLY,
                InternalMatchesConstants::NEW           => self::_NEW,
        ];

        return $mapping[ $match_type ] ?? strtolower( $match_type );
    }

}