<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 12/05/15
 * Time: 15.04
 * 
 */

class Analysis_PayableRates {

    public static $DEFAULT_PAYABLE_RATES = array(
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
            'MT'          => 85
    );

    private static $langPair2MTpayableRates = array(
            "en" => array(
                    "it" => array(
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
                            'MT'          => 80
                    ),
                    "fr" => array(
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
                            'MT'          => 80
                    ),
                    "pt" => array(
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
                            'MT'          => 80
                    ),
                    "es" => array(
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
                            'MT'          => 80
                    ),
                    "nl" => array(
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
                            'MT'          => 80
                    ),
                    "pl" => array(
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
                            'MT'          => 90
                    ),
                    "uk" => array(
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
                            'MT'          => 90
                    ),
                    "hi" => array(
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
                            'MT'          => 90
                    ),
                    "fi" => array(
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
                            'MT'          => 90
                    ),
                    "tr" => array(
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
                            'MT'          => 90
                    ),
                    "ru" => array(
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
                            'MT'          => 90
                    ),
                    "zh" => array(
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
                            'MT'          => 90
                    ),
                    "ar" => array(
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
                            'MT'          => 90
                    ),
                    "ko" => array(
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
                            'MT'          => 90
                    ),
                    "lt" => array(
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
                            'MT'          => 90
                    ),
                    "ja" => array(
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
                            'MT'          => 90
                    ),
                    "he" => array(
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
                            'MT'          => 90
                    ),
                    "sr" => array(
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
                            'MT'          => 90
                    )
            )
    );

    /**
     * Get the payable rate for a given langpair.
     * NB: the map is supposed to be symmetric. If there is the need to make it asymmetric, please change this method
     * and the corresponding map.
     *
     * @param $source string The first two chars of the source language name in RFC3066<br/>
     *                       Example: <i>en-US</i> --> <b>en</b>
     * @param $target string The first two chars of the target language name in RFC3066<br/>
     *                       Example: <i>en-US</i> --> <b>en</b>
     * @return string
     */
    public static function getPayableRates( $source, $target ) {

        $ret = self::$DEFAULT_PAYABLE_RATES;

        //search source -> target pair
        if ( isset( self::$langPair2MTpayableRates[ $source ][ $target ] ) ) {
            $ret = self::$langPair2MTpayableRates[ $source ][ $target ];

        } elseif ( isset( self::$langPair2MTpayableRates[ $target ][ $source ] ) ) { //search target -> source pair
            $ret = self::$langPair2MTpayableRates[ $target ][ $source ];
        }

        return $ret;

    }


    /**
     * This function returns the dynamic payable rate given a post-editing effort
     * @param $pee float
     * @return float
     */
    public static function pee2payable( $pee ) {
        $pee = floatval($pee);

        if ( $pee < 0 ) {
            $pee = 0;
        }
        if ( $pee > 100 ) {
            $pee = 100;
        }

        $x_2_coef = -0.00032;
        $x_coef   = 0.034;
        $constant = 0.1;

        $payable = ($x_2_coef * ( pow( $pee, 2 ) ) + $x_coef * $pee + $constant);
        $payable = round( 100 * $payable , 1);
//        $payable = self::roundUpToAny( $payable, 5);

        if ( $payable < 75 ) {
            $payable = 75;
        }
        if ( $payable > 95 ) {
            $payable = 95;
        }

        return str_replace( ".", ",", $payable );
    }

    private static function roundUpToAny($n,$x=5) {
        return (round($n)%$x === 0) ? round($n) : round(($n+$x/2)/$x)*$x;
    }

}