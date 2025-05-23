<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 23/05/25
 * Time: 11:55
 *
 */

namespace AsyncTasks\Workers\Traits;

trait SortMatchesTrait {

    /**
     * Compare match scores between TM records and MT records when they are external to MyMemory
     * Compares two associative arrays based on their 'match' and 'ICE' values.
     *
     * The function first evaluates the 'match' values of the two arrays:
     * - If the 'match' values are equal, it prioritizes arrays with the 'ICE' key set to true:
     *   - Returns -1 if the first array has 'ICE' set to true and the second does not.
     *   - Returns 1 if the second array has 'ICE' set to true and the first does not.
     *   - Returns 0 if both or neither have the 'ICE' key set to true.
     * - If the 'match' values are not equal, it returns:
     *   - 1 if the 'match' value of the first array is less than the second.
     *   - -1 if the 'match' value of the first array is greater than the second.
     *
     * @param array $a The first array to compare, containing 'match' and optionally 'ICE'.
     * @param array $b The second array to compare, containing 'match' and optionally 'ICE'.
     *
     * @return int Returns -1, 0, or 1 based on the comparison logic.
     */
    private static function compareScoreDesc( array $a, array $b ): int {

        // Check if the 'ICE' key is set and cast it to a boolean
        $aIsICE = (bool)( $a[ 'ICE' ] ?? false );
        $bIsICE = (bool)( $b[ 'ICE' ] ?? false );

        // Convert 'match' values to float for comparison
        $aMatch = floatval( $a[ 'match' ] );
        $bMatch = floatval( $b[ 'match' ] );

        // If 'match' values are equal, compare based on 'ICE' values
        if ( $aMatch == $bMatch ) {
            if ( $aIsICE && !$bIsICE ) {
                return -1; // The First array has 'ICE' set to true, the second does not
            }
            if ( !$aIsICE && $bIsICE ) {
                return 1; // The Second array has 'ICE' set to true, the first does not
            }

            return 0; // Both or neither have 'ICE' set to true
        }

        // If 'match' values are not equal, return based on their comparison
        return ( $aMatch < $bMatch ? 1 : -1 );
    }

}