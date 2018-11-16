<?php

/*
   This code is copyrighted and property of Translated s.r.l.
   Should not be distrubuted.
   This is made available for Matecat partners for executing the field test.
   Thank you for keeping is confidential.
 */

class MyMemory {

    public static function TMS_MATCH( $seg1, $seg2, $language = false ) {

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
        $c = count( self::my_array_xor( $temp1[ 0 ], $temp2[ 0 ] ) );

        $seg1 = preg_replace( '/<.*?>/s', ' ', $seg1 );
        $seg2 = preg_replace( '/<.*?>/s', ' ', $seg2 );

        $penalty += 0.01 * $c;

        // Penalty for different numbers
        $temp1 = '';
        $temp2 = '';
        preg_match_all( '/(0-9|,|\.)+/u', $seg1, $temp1 );
        preg_match_all( '/(0-9|,|\.)+/u', $seg2, $temp2 );
        $c = count( self::my_array_xor( $temp1[ 0 ], $temp2[ 0 ] ) );

        $seg1 = preg_replace( '/(0-9|,|\.)+/u', ' ', $seg1 );
        $seg2 = preg_replace( '/(0-9|,|\.)+/u', ' ', $seg2 );

        $penalty_placeable = 0.01 * $c;

        // Penalties Punctuation
        // Differs from numbers because if A has punt and B does not, it's not that bad as if a number is missing.
        $temp1 = '';
        $temp2 = '';
        preg_match_all( '/(\p{P}|\p{S}|\x{00a0})+/u', $seg1, $temp1 );
        preg_match_all( '/(\p{P}|\p{S}|\x{00a0})+/u', $seg2, $temp2 );
        $c = count( self::my_array_xor( $temp1[ 0 ], $temp2[ 0 ] ) );

        $seg1 = preg_replace( '/(\p{P}|\p{S}|\x{00a0})+/u', ' ', $seg1 );
        $seg2 = preg_replace( '/(\p{P}|\p{S}|\x{00a0})+/u', ' ', $seg2 );
        $penalty_placeable += 0.02 * $c;

        // penalty per case sensitive / formatting
        $penalty_formatting = 0.00;

        // I remove all double spaces I introduced
        $seg1 = preg_replace( '/[ ]+/u', ' ', $seg1 );
        $seg2 = preg_replace( '/[ ]+/u', ' ', $seg2 );

        if ( $language !== FALSE && CatUtils::isCJK( $language ) ) {
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

        $tms_match = self::TMS_ARRAY_MATCH( $a_lower, $b_lower );
        // if ($tms_match > 0 ) is true, the following member is considered, otherwise it is multiplied by 0 ( = false)
        // This is useful to skip penalty in case that one of the 2 strings is empty;
        $result = $tms_match - ( $penalty + $penalty_formatting + $penalty_placeable );
        if ( trim( $originalSeg1 ) != trim( $originalSeg2 ) && $result == 1 ) {
            $result -= 0.01;
        }

        $result = min( 1, max( 0, $result ) );

        return $result;
    }

    public static function TMS_ARRAY_MATCH( $array1, $array2 ) {

        // No Longer symmetric
        // Important:
        // Array1 is the segment to translate
        // Array2 is the suggestion
        // es. control panel -> panel = lev match 75%
        $min_words_norm = 4;

        $aliases = array_flip( array_values( array_unique( array_merge( $array1, $array2 ) ) ) );

        // Is the string is longer than 254 words (does not make sense) I cannot use levenshtein of oliver.
        if ( ( count( $aliases ) > 254 ) OR ( count( $aliases ) > 254 ) ) {
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

    protected static function CJK_tokenizer( $text ) {
        $words = explode( ' ', ( $text ) );
        //$words = preg_split("/\\p{Z}+/", ($text));
        //If characters are not latin then use bigram
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
        if ( $length <= 1 ) {
            return $chrArray;
        } else {
            for ( $i = 0; $i < $length - 1; $i++ ) {
                $chrArray[ $i ] = $chrArray[ $i ] . $chrArray[ $i + 1 ];
            }
            array_pop( $chrArray );

            return $chrArray;
        }
    }

    // I expect this to be in PHP in the future...
    public static function my_array_xor( $array_a, $array_b ) {
        $union_array     = array_merge( $array_a, $array_b );
        $intersect_array = array_intersect( $array_a, $array_b );

        return array_diff( $union_array, $intersect_array );
    }

    public static function diff( $old, $new ) {

        $maxlen = 0;

        foreach ( $old as $oindex => $ovalue ) {
            $nkeys = array_keys( $new, $ovalue );
            foreach ( $nkeys as $nindex ) {
                $matrix[ $oindex ][ $nindex ] = isset( $matrix[ $oindex - 1 ][ $nindex - 1 ] ) ? $matrix[ $oindex - 1 ][ $nindex - 1 ] + 1 : 1;
                if ( $matrix[ $oindex ][ $nindex ] > $maxlen ) {
                    $maxlen = $matrix[ $oindex ][ $nindex ];
                    $omax   = $oindex + 1 - $maxlen;
                    $nmax   = $nindex + 1 - $maxlen;
                }
            }
        }

        if ( $maxlen == 0 ) {
            return [ [ 'd' => $old, 'i' => $new ] ];
        }

        return array_merge(
                self::diff( array_slice( $old, 0, $omax ), array_slice( $new, 0, $nmax ) ),
                array_slice( $new, $nmax, $maxlen ),
                self::diff( array_slice( $old, $omax + $maxlen ),
                array_slice( $new, $nmax + $maxlen ) )
        );

    }

    public static function diff_html( $old, $new, $by_word = true ) {

        // No diff no work
        if ( $old == $new ) {
            return $new;
        }

        if ( strlen( $old ) <= 254 AND strlen( $old ) <= 254 ) {
            if ( levenshtein( $new, $old ) <= 2 ) {
                $by_word = false;
            }
        }

        if ( $by_word == true ) {
            $sep = ' ';
            $old_array = explode( ' ', $old );
            $new_array = explode( ' ', $new );
        } else {
            $sep = '';
            $old_array = preg_split( '//u', $old, -1, PREG_SPLIT_NO_EMPTY );
            $new_array = preg_split( '//u', $new, -1, PREG_SPLIT_NO_EMPTY );
        }

        $diff = self::diff( $old_array, $new_array );

        $array_patterns = array(
                "/&#x0A;/",
                "/&#x0D;/",
                "/&#x0D;&#x0A;/",
                "/&#x09;/",
                "/&#xA0;|&#nbsp;/",
        );

        $array_replacements = array(
                '<span class="_0A"></span><br />',
                '<span class="_0D"></span><br />',
                '<span class="_0D0A"></span><br />',
                '<span class="_tab">&#9;</span>',
                '<span class="_nbsp">&nbsp;</span>',
        );

        $ret = '';
        foreach ( $diff as $k ) {

            if ( is_array( $k ) ) {

                $k[ 'd' ] = preg_replace( $array_patterns, $array_replacements, $k[ 'd' ] );
                $k[ 'i' ] = preg_replace( $array_patterns, $array_replacements, $k[ 'i' ] );

                $ret .= ( !empty( $k[ 'd' ] ) ? "<strike><span style=\"color:red;\">" . implode( $sep, $k[ 'd' ] ) . "</span></strike>$sep" : $sep ) .
                        ( !empty( $k[ 'i' ] ) ? "<span style=\"color:blue\">" . implode( $sep, $k[ 'i' ] ) . "</span>$sep" : $sep );
            } else {

                $k = preg_replace( $array_patterns, $array_replacements, $k );
                $ret .= $k . $sep;
            }
        }

        return $ret;
    }

}
