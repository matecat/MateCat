<?php

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 12/05/15
 * Time: 15.04
 *
 */
class Analysis_PayableRates {

    public static $DEFAULT_PAYABLE_RATES = [
            'NO_MATCH'    => 100,
            '50%-74%'     => 100,
        //            '75%-99%'     => 60,
            '75%-84%'     => 60,
            '85%-94%'     => 60,
            '95%-99%'     => 60,
            '100%'        => 30,
            '100%_PUBLIC' => 30,
            'REPETITIONS' => 30,
            'INTERNAL'    => 60,
            'MT'          => 82
    ];

    protected static $langPair2MTpayableRates = [
            "en" => [
                    "it" => [
                            'NO_MATCH'    => 100,
                            '50%-74%'     => 100,
                        //'75%-99%'     => 60,
                            '75%-84%'     => 60,
                            '85%-94%'     => 60,
                            '95%-99%'     => 60,
                            '100%'        => 30,
                            '100%_PUBLIC' => 30,
                            'REPETITIONS' => 30,
                            'INTERNAL'    => 60,
                            'MT'          => 77
                    ],
                    "fr" => [
                            'NO_MATCH'    => 100,
                            '50%-74%'     => 100,
                        //'75%-99%'     => 60,
                            '75%-84%'     => 60,
                            '85%-94%'     => 60,
                            '95%-99%'     => 60,
                            '100%'        => 30,
                            '100%_PUBLIC' => 30,
                            'REPETITIONS' => 30,
                            'INTERNAL'    => 60,
                            'MT'          => 77
                    ],
                    "pt" => [
                            'NO_MATCH' => 100,
                            '50%-74%'  => 100,
                        //'75%-99%'     => 60,
                            '75%-84%'  => 60,
                            '85%-94%'  => 60,
                            '95%-99%'  => 60,
                            '100%'     => 30,

                            '100%_PUBLIC' => 30,
                            'REPETITIONS' => 30,
                            'INTERNAL'    => 60,
                            'MT'          => 77
                    ],
                    "es" => [
                            'NO_MATCH'    => 100,
                            '50%-74%'     => 100,
                        //'75%-99%'     => 60,
                            '75%-84%'     => 60,
                            '85%-94%'     => 60,
                            '95%-99%'     => 60,
                            '100%'        => 30,
                            '100%_PUBLIC' => 30,
                            'REPETITIONS' => 30,
                            'INTERNAL'    => 60,
                            'MT'          => 77
                    ],
                    "nl" => [
                            'NO_MATCH'    => 100,
                            '50%-74%'     => 100,
                        //'75%-99%'     => 60,
                            '75%-84%'     => 60,
                            '85%-94%'     => 60,
                            '95%-99%'     => 60,
                            '100%'        => 30,
                            '100%_PUBLIC' => 30,
                            'REPETITIONS' => 30,
                            'INTERNAL'    => 60,
                            'MT'          => 77
                    ],
                    "pl" => [
                            'NO_MATCH'    => 100,
                            '50%-74%'     => 100,
                        //'75%-99%'     => 60,
                            '75%-84%'     => 60,
                            '85%-94%'     => 60,
                            '95%-99%'     => 60,
                            '100%'        => 30,
                            '100%_PUBLIC' => 30,
                            'REPETITIONS' => 30,
                            'INTERNAL'    => 60,
                            'MT'          => 87
                    ],
                    "uk" => [
                            'NO_MATCH'    => 100,
                            '50%-74%'     => 100,
                        //'75%-99%'     => 60,
                            '75%-84%'     => 60,
                            '85%-94%'     => 60,
                            '95%-99%'     => 60,
                            '100%'        => 30,
                            '100%_PUBLIC' => 30,
                            'REPETITIONS' => 30,
                            'INTERNAL'    => 60,
                            'MT'          => 87
                    ],
                    "hi" => [
                            'NO_MATCH'    => 100,
                            '50%-74%'     => 100,
                        //'75%-99%'     => 60,
                            '75%-84%'     => 60,
                            '85%-94%'     => 60,
                            '95%-99%'     => 60,
                            '100%'        => 30,
                            '100%_PUBLIC' => 30,
                            'REPETITIONS' => 30,
                            'INTERNAL'    => 60,
                            'MT'          => 87
                    ],
                    "fi" => [
                            'NO_MATCH'    => 100,
                            '50%-74%'     => 100,
                        //'75%-99%'     => 60,
                            '75%-84%'     => 60,
                            '85%-94%'     => 60,
                            '95%-99%'     => 60,
                            '100%'        => 30,
                            '100%_PUBLIC' => 30,
                            'REPETITIONS' => 30,
                            'INTERNAL'    => 60,
                            'MT'          => 87
                    ],
                    "tr" => [
                            'NO_MATCH'    => 100,
                            '50%-74%'     => 100,
                        //'75%-99%'     => 60,
                            '75%-84%'     => 60,
                            '85%-94%'     => 60,
                            '95%-99%'     => 60,
                            '100%'        => 30,
                            '100%_PUBLIC' => 30,
                            'REPETITIONS' => 30,
                            'INTERNAL'    => 60,
                            'MT'          => 87
                    ],
                    "ru" => [
                            'NO_MATCH'    => 100,
                            '50%-74%'     => 100,
                        //'75%-99%'     => 60,
                            '75%-84%'     => 60,
                            '85%-94%'     => 60,
                            '95%-99%'     => 60,
                            '100%'        => 30,
                            '100%_PUBLIC' => 30,
                            'REPETITIONS' => 30,
                            'INTERNAL'    => 60,
                            'MT'          => 87
                    ],
                    "zh" => [
                            'NO_MATCH'    => 100,
                            '50%-74%'     => 100,
                        //'75%-99%'     => 60,
                            '75%-84%'     => 60,
                            '85%-94%'     => 60,
                            '95%-99%'     => 60,
                            '100%'        => 30,
                            '100%_PUBLIC' => 30,
                            'REPETITIONS' => 30,
                            'INTERNAL'    => 60,
                            'MT'          => 87
                    ],
                    "ar" => [
                            'NO_MATCH'    => 100,
                            '50%-74%'     => 100,
                        //'75%-99%'     => 60,
                            '75%-84%'     => 60,
                            '85%-94%'     => 60,
                            '95%-99%'     => 60,
                            '100%'        => 30,
                            '100%_PUBLIC' => 30,
                            'REPETITIONS' => 30,
                            'INTERNAL'    => 60,
                            'MT'          => 87
                    ],
                    "ko" => [
                            'NO_MATCH'    => 100,
                            '50%-74%'     => 100,
                        //'75%-99%'     => 60,
                            '75%-84%'     => 60,
                            '85%-94%'     => 60,
                            '95%-99%'     => 60,
                            '100%'        => 30,
                            '100%_PUBLIC' => 30,
                            'REPETITIONS' => 30,
                            'INTERNAL'    => 60,
                            'MT'          => 87
                    ],
                    "lt" => [
                            'NO_MATCH'    => 100,
                            '50%-74%'     => 100,
                        //'75%-99%'     => 60,
                            '75%-84%'     => 60,
                            '85%-94%'     => 60,
                            '95%-99%'     => 60,
                            '100%'        => 30,
                            '100%_PUBLIC' => 30,
                            'REPETITIONS' => 30,
                            'INTERNAL'    => 60,
                            'MT'          => 87
                    ],
                    "ja" => [
                            'NO_MATCH'    => 100,
                            '50%-74%'     => 100,
                        //'75%-99%'     => 60,
                            '75%-84%'     => 60,
                            '85%-94%'     => 60,
                            '95%-99%'     => 60,
                            '100%'        => 30,
                            '100%_PUBLIC' => 30,
                            'REPETITIONS' => 30,
                            'INTERNAL'    => 60,
                            'MT'          => 87
                    ],
                    "he" => [
                            'NO_MATCH'    => 100,
                            '50%-74%'     => 100,
                        //'75%-99%'     => 60,
                            '75%-84%'     => 60,
                            '85%-94%'     => 60,
                            '95%-99%'     => 60,
                            '100%'        => 30,
                            '100%_PUBLIC' => 30,
                            'REPETITIONS' => 30,
                            'INTERNAL'    => 60,
                            'MT'          => 87
                    ],
                    "sr" => [
                            'NO_MATCH'    => 100,
                            '50%-74%'     => 100,
                        //'75%-99%'     => 60,
                            '75%-84%'     => 60,
                            '85%-94%'     => 60,
                            '95%-99%'     => 60,
                            '100%'        => 30,
                            '100%_PUBLIC' => 30,
                            'REPETITIONS' => 30,
                            'INTERNAL'    => 60,
                            'MT'          => 87
                    ]
            ]
    ];

    /**
     * Get the payable rate for a given langpair.
     * NB: the map is supposed to be symmetric. If there is the need to make it asymmetric, please change this method
     * and the corresponding map.
     *
     * @param $source        string The first two chars of the source language name in RFC3066<br/>
     *                       Example: <i>en-US</i> --> <b>en</b>
     * @param $target        string The first two chars of the target language name in RFC3066<br/>
     *                       Example: <i>en-US</i> --> <b>en</b>
     *
     * @return array
     */
    public static function getPayableRates( $source, $target ) {

        $ret = static::$DEFAULT_PAYABLE_RATES;

        //search source -> target pair
        if ( isset( static::$langPair2MTpayableRates[ $source ][ $target ] ) ) {
            $ret = static::$langPair2MTpayableRates[ $source ][ $target ];

        } elseif ( isset( static::$langPair2MTpayableRates[ $target ][ $source ] ) ) { //search target -> source pair
            $ret = static::$langPair2MTpayableRates[ $target ][ $source ];
        }

        return $ret;

    }


    /**
     * This function returns the dynamic payable rate given a post-editing effort
     *
     * @param $pee float
     *
     * @return float
     */
    public static function pee2payable( $pee ) {
        $pee = floatval( $pee );

        // payable = ( aX^2 + bX + c ) * 100
        return round( ( -0.00032 * ( pow( $pee, 2 ) ) + 0.034 * $pee + 0.1 ) * 100, 1 );
    }

    public static function proposalPee( $payable ) {
        return min( 95, max( 75, $payable ) );
    }

    public static function wordsSavingDiff( $actual_payable, $proposal_payable, $word_count ) {
        return round( ( $actual_payable - $proposal_payable ) * $word_count );
    }

    private static function roundUpToAny( $n, $x = 5 ) {
        return ( round( $n ) % $x === 0 ) ? round( $n ) : round( ( $n + $x / 2 ) / $x ) * $x;
    }

}