<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 08/07/25
 * Time: 11:21
 *
 */

namespace Utils\AsyncTasks\Workers\Traits;

use Model\Analysis\Constants\InternalMatchesConstants;

trait MatchesComparator {

    /**
     * @param array $mt_result
     * @param array $matches
     *
     * @return array
     */
    protected function _sortMatches( array $mt_result, array $matches ): array {
        if ( !empty( $mt_result ) ) {
            $matches[] = $mt_result; // append the MT result to matches
        }

        usort( $matches, [ "static", "__compareScoreDesc" ] );

        return $matches;
    }

    /**
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
    private function __compareScoreDesc( array $a, array $b ): int {

        // Check if the 'ICE' key is set and cast it to a boolean
        $aIsICE = (bool)( $a[ 'ICE' ] ?? false );
        $bIsICE = (bool)( $b[ 'ICE' ] ?? false );

        // Convert 'match' values to float for comparison
        $aMatch = floatval( $a[ 'match' ] );
        $bMatch = floatval( $b[ 'match' ] );

        // If 'match' values are equal, compare based on 'ICE' and MT match since MT matches can be customized and should be prioritized over equivalent matches
        if ( $aMatch == $bMatch ) {
            $conditions = [
                    [ $aIsICE && !$bIsICE, -1 ], // The First array has 'ICE' set to true
                    [ !$aIsICE && $bIsICE, 1 ],  // The Second array has 'ICE' set to true
                    [ $this->isMtMatch( $a ), -1 ], // The First array is an MT match
                    [ $this->isMtMatch( $b ), 1 ]   // The Second array is an MT match
            ];

            foreach ( $conditions as [ $condition, $result ] ) {
                if ( $condition ) {
                    return $result;
                }
            }

            return 0; // Both arrays are equal in priority
        }

        // If 'match' values are not equal, return based on their comparison
        return ( $aMatch < $bMatch ? 1 : -1 );
    }

    public function isMtMatch( array $match ): bool {
        return stripos( $match[ 'created_by' ], InternalMatchesConstants::MT ) !== false;
    }

}