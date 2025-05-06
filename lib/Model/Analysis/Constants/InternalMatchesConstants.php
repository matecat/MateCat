<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 06/05/25
 * Time: 11:30
 *
 */

namespace Model\Analysis\Constants;

class InternalMatchesConstants {

    // generic TM but not used as a value in the database data
    const TM = "TM";

    const NO_MATCH            = "NO_MATCH";
    const NEW                 = "NEW";
    const TM_50_74            = "50%-74%";
    const TM_75_84            = "75%-84%";
    const TM_85_94            = "85%-94%";
    const TM_95_99            = "95%-99%";
    const TM_100              = "100%";
    const TM_100_PUBLIC       = "100%_PUBLIC";
    const TM_ICE              = "ICE";
    const MT                  = "MT";
    const REPETITIONS         = 'REPETITIONS';
    const INTERNAL            = 'INTERNAL';
    const NUMBERS_ONLY        = 'NUMBERS_ONLY';
    const ICE_MT              = "ICE_MT";
    const TOP_QUALITY_MT      = 'TOP_QUALITY_MT';
    const HIGHER_QUALITY_MT   = 'HIGHER_QUALITY_MT';
    const STANDARD_QUALITY_MT = 'STANDARD_QUALITY_MT';

}