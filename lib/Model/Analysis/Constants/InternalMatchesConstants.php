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
    const string TM = "TM";

    const string NO_MATCH            = "NO_MATCH";
    const string NEW                 = "NEW";
    const string TM_50_74            = "50%-74%";
    const string TM_75_84            = "75%-84%";
    const string TM_85_94            = "85%-94%";
    const string TM_95_99            = "95%-99%";
    const string TM_100              = "100%";
    const string TM_100_PUBLIC       = "100%_PUBLIC";
    const string TM_ICE              = "ICE";
    const string MT                  = "MT";
    const string REPETITIONS         = 'REPETITIONS';
    const string INTERNAL            = 'INTERNAL';
    const string NUMBERS_ONLY        = 'NUMBERS_ONLY';
    const string ICE_MT              = "ICE_MT";
    const string TOP_QUALITY_MT      = 'TOP_QUALITY_MT';
    const string HIGHER_QUALITY_MT   = 'HIGHER_QUALITY_MT';
    const string STANDARD_QUALITY_MT = 'STANDARD_QUALITY_MT';

    /**
     * These values are not inserted in the database, they are needed to maintain the cross-correlation between the new and the old field names
     * @see MTQEMatchTypeNamesConstants
     * @see StandardMatchTypeNamesConstants
     */
    const string TM_100_PUBLIC_MT_QE = 'TM_100_PUBLIC';
    const string TM_100_MT_QE        = 'TM_100';

}