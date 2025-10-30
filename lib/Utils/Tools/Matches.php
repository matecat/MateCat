<?php

namespace Utils\Tools;

class Matches {

    public static function get( $seg1, $seg2, $language = false ) {

        $originalSeg1 = $seg1;
        $originalSeg2 = $seg2;

        $penalty = 0;

        $seg1 = mb_strtolower( $seg1, "UTF-8" );
        $seg2 = mb_strtolower( $seg2, "UTF-8" );

        // xml apos
        $seg1 = str_replace( '&apos;', "'", $seg1 );
        $seg2 = str_replace( '&apos;', "'", $seg2 );

        // Tag Penalties
        preg_match_all( '/<.*?>/s', $seg1, $temp1 );
        preg_match_all( '/<.*?>/s', $seg2, $temp2 );
        $c = count( self::array_xor( $temp1[ 0 ], $temp2[ 0 ] ) );

        $seg1 = preg_replace( '/<.*?>/s', ' ', $seg1 );
        $seg2 = preg_replace( '/<.*?>/s', ' ', $seg2 );

        $penalty += 0.01 * $c;

        // Penalty for different numbers
        $temp1 = '';
        $temp2 = '';
        preg_match_all( '/(0-9|,|\.)+/u', $seg1, $temp1 );
        preg_match_all( '/(0-9|,|\.)+/u', $seg2, $temp2 );
        $c = count( self::array_xor( $temp1[ 0 ], $temp2[ 0 ] ) );

        $seg1 = preg_replace( '/(0-9|,|\.)+/u', ' ', $seg1 );
        $seg2 = preg_replace( '/(0-9|,|\.)+/u', ' ', $seg2 );

        $penalty_placeable = 0.01 * $c;

        // Penalty Punctuation
        // Differs from numbers because if A has punt and B doesn't, it's not that bad as if a number is missing.
        $temp1 = '';
        $temp2 = '';
        preg_match_all( '/(\p{P}|\p{S}|\x{00a0})+/u', $seg1, $temp1 );
        preg_match_all( '/(\p{P}|\p{S}|\x{00a0})+/u', $seg2, $temp2 );
        $c = count( self::array_xor( $temp1[ 0 ], $temp2[ 0 ] ) );

        $seg1              = preg_replace( '/(\p{P}|\p{S}|\x{00a0})+/u', ' ', $seg1 );
        $seg2              = preg_replace( '/(\p{P}|\p{S}|\x{00a0})+/u', ' ', $seg2 );
        $penalty_placeable += 0.02 * $c;

        // penalty per case-sensitive / formatting
        $penalty_formatting = 0.00;

        // Remove all double spaces
        $seg1 = preg_replace( '/ +/u', ' ', $seg1 );
        $seg2 = preg_replace( '/ +/u', ' ', $seg2 );

        if ( !empty( $language ) && CatUtils::isCJK( $language ) ) {
            $a = self::CJK_tokenizer( $seg1 );
            $b = self::CJK_tokenizer( $seg2 );
        } else {
            $a = explode( ' ', ( $seg1 ) );
            $b = explode( ' ', ( $seg2 ) );
        }
        $a = array_filter( $a, 'trim' );
        $b = array_filter( $b, 'trim' );

        $a_lower = array_map( 'mb_strtolower', $a, array_fill( 0, count( $a ), 'UTF-8' ) );
        $b_lower = array_map( 'mb_strtolower', $b, array_fill( 0, count( $b ), 'UTF-8' ) );
        if ( $a_lower === $b_lower && $a !== $b ) {
            $penalty_formatting = 0.02;
        }

        $tms_match = self::arrayDistance( $a_lower, $b_lower );
        // if ($tms_match > 0) is true, the following member is considered, otherwise it's multiplied by 0 (= false)
        // This is useful to skip penalty in case that one of the 2 strings is empty
        $result = $tms_match - ( $penalty + $penalty_formatting + $penalty_placeable );
        if ( trim( $originalSeg1 ) != trim( $originalSeg2 ) && $result == 1 ) {
            $result -= 0.01;
        }

        return min( 1, max( 0, $result ) );
    }

    protected static function arrayDistance( $array1, $array2 ) {

        // No Longer symmetric
        // *** Important
        // Array1 is the segment to translate
        // Array2 is the suggestion
        // es.
        // Control panel -> panel = lev match 75%
        $min_words_norm = 4;

        $aliases = array_flip( array_values( array_unique( array_merge( $array1, $array2 ) ) ) );

        // If the string is longer than 254 words (doesn't make sense), can't use levenshtein of oliver.
        if ( count( $aliases ) > 254 ) {
            return -1;
        }

        $stringA = '';
        $stringB = '';

        foreach ( $array1 as $entry ) {
            $stringA .= CatUtils::unicode2chr( $aliases[ $entry ] );
            if ( mb_strlen( $entry ) > 4 ) {
                $stringA .= chr( $aliases[ $entry ] );
            }
        }

        foreach ( $array2 as $entry ) {
            $stringB .= CatUtils::unicode2chr( $aliases[ $entry ] );
            if ( mb_strlen( $entry ) > 4 ) {
                $stringB .= chr( $aliases[ $entry ] );
            }
        }

        similar_text( $stringA, $stringB, $p );

        $la   = strlen( $stringA );
        $lb   = strlen( $stringB );
        $lmax = max( $la, $lb );

        return ( $p > 0 ? ( 1 - $lmax / max( $lmax, $min_words_norm ) * ( 1 - $p / 100 ) ) : 0 );

    }

    protected static function CJK_tokenizer( $text ): array {
        $words = explode( ' ', ( $text ) );
        //If characters aren't latin, then use bigram
        if ( preg_match( '/[^\\p{Common}\\p{Latin}]/u', $text ) ) {
            $number_of_words = count( $words );
            $tokens          = [];
            for ( $i = 0; $i < $number_of_words; $i++ ) {
                if ( preg_match( '/[^\\p{Common}\\p{Latin}]/u', $words[ $i ] ) ) {
                    $tokens = array_merge( $tokens, self::compute_bigram( $words[ $i ] ) );
                } else {
                    $tokens[] = $words[ $i ];
                }
            }
        } else {
            $tokens = $words;
        }

        return $tokens;
    }

    protected static function compute_bigram( $text ) {
        $chrArray = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY );
        $length   = count( $chrArray );
        if ( $length > 1 ) {
            for ( $i = 0; $i < $length - 1; $i++ ) {
                $chrArray[ $i ] = $chrArray[ $i ] . $chrArray[ $i + 1 ];
            }
            array_pop( $chrArray );
        }

        return $chrArray;
    }

    /**
     * @param array $array_a
     * @param array $array_b
     *
     * @return array
     */
    // Expect this to be in PHP in the future
    protected static function array_xor( array $array_a, array $array_b ): array {
        $union_array     = array_merge( $array_a, $array_b );
        $intersect_array = array_intersect( $array_a, $array_b );

        return array_diff( $union_array, $intersect_array );
    }

}
