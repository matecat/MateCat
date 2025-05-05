<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 14/11/23
 * Time: 12:06
 *
 */

namespace API\App\Json\Analysis\Constants;

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
        switch ( $match_type ) {
            case self::REPETITIONS:
                return strtoupper( self::REPETITIONS );
            case self::TM_100:
                return '100%';
            case self::TM_100_PUBLIC :
                return '100%_PUBLIC';
            case self::ICE:
                return strtoupper( self::ICE );
            case self::ICE_MT:
                return strtoupper( self::ICE_MT );
            case self::TOP_QUALITY_MT:
                return strtoupper( self::TOP_QUALITY_MT );
            case self::HIGHER_QUALITY_MT:
                return strtoupper( self::HIGHER_QUALITY_MT );
            case self::STANDARD_QUALITY_MT:
            default:
                return strtoupper( self::STANDARD_QUALITY_MT );
        }

    }

    public static function toExternalMatchTypeValue( string $match_type ): string {
        switch ( $match_type ) {
            case strtoupper( self::REPETITIONS ):
                return self::REPETITIONS;
            case '100%':
                return self::TM_100;
            case '100%_PUBLIC'  :
                return self::TM_100_PUBLIC;
            case strtoupper( self::ICE ):
                return self::ICE;
            case strtoupper( self::ICE_MT ):
                return self::ICE_MT;
            case strtoupper( self::TOP_QUALITY_MT ):
                return self::TOP_QUALITY_MT;
            case strtoupper( self::HIGHER_QUALITY_MT ):
                return self::HIGHER_QUALITY_MT;
            case strtoupper( self::STANDARD_QUALITY_MT ):
            default:
                return self::STANDARD_QUALITY_MT;

        }

    }

}