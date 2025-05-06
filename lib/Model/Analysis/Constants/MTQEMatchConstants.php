<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 14/11/23
 * Time: 12:06
 *
 */

namespace Model\Analysis\Constants;

class MTQEMatchConstants extends AbstractConstants {

    protected static string $workflow_type = 'mtqe';

    public static function getWorkflowType(): string {
        return static::$workflow_type;
    }

    /*
     * These constants refer to the values sent by the APIs and need to be converted
     * into the values that are used internally and to be inserted into the database.
     */
    const TM_100              = "tm_100";
    const TM_100_PUBLIC       = "tm_100_public";
    const ICE                 = "ice";
    const ICE_MT              = "ice_MT";
    const REPETITIONS         = 'repetitions';
    const TOP_QUALITY_MT      = 'top_quality_mt';
    const HIGHER_QUALITY_MT   = 'higher_quality_mt';
    const STANDARD_QUALITY_MT = 'standard_quality_mt';

    protected static array $forValue = [
            self::TM_100              => self::TM_100,
            self::TM_100_PUBLIC       => self::TM_100_PUBLIC,
            self::ICE                 => self::ICE,
            self::TOP_QUALITY_MT      => self::TOP_QUALITY_MT,
            self::HIGHER_QUALITY_MT   => self::HIGHER_QUALITY_MT,
            self::STANDARD_QUALITY_MT => self::STANDARD_QUALITY_MT,
            self::ICE_MT              => self::ICE_MT,
            self::REPETITIONS         => self::REPETITIONS,
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
                self::REPETITIONS         => InternalMatchesConstants::REPETITIONS,
                self::TM_100              => InternalMatchesConstants::TM_100,
                self::TM_100_PUBLIC       => InternalMatchesConstants::TM_100_PUBLIC,
                self::ICE                 => InternalMatchesConstants::TM_ICE,
                self::ICE_MT              => InternalMatchesConstants::ICE_MT,
                self::TOP_QUALITY_MT      => InternalMatchesConstants::TOP_QUALITY_MT,
                self::HIGHER_QUALITY_MT   => InternalMatchesConstants::HIGHER_QUALITY_MT,
                self::STANDARD_QUALITY_MT => InternalMatchesConstants::STANDARD_QUALITY_MT,
        ];

        return $mapping[ $match_type ] ?? strtoupper( $match_type );
    }

    public static function toExternalMatchTypeValue( string $match_type ): string {
        $mapping = [
                InternalMatchesConstants::REPETITIONS         => self::REPETITIONS,
                InternalMatchesConstants::TM_100              => self::TM_100,
                InternalMatchesConstants::TM_100_PUBLIC       => self::TM_100_PUBLIC,
                InternalMatchesConstants::TM_ICE              => self::ICE,
                InternalMatchesConstants::ICE_MT              => self::ICE_MT,
                InternalMatchesConstants::TOP_QUALITY_MT      => self::TOP_QUALITY_MT,
                InternalMatchesConstants::HIGHER_QUALITY_MT   => self::HIGHER_QUALITY_MT,
                InternalMatchesConstants::STANDARD_QUALITY_MT => self::STANDARD_QUALITY_MT,
        ];

        return $mapping[ $match_type ] ?? strtolower( $match_type );
    }

}