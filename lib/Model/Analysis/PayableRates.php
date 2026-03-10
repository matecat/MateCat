<?php

namespace Model\Analysis;

use Matecat\Locales\Languages;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 12/05/15
 * Time: 15.04
 *
 */
class PayableRates
{

    public static array $DEFAULT_PAYABLE_RATES = [
        'NO_MATCH' => 100,
        '50%-74%' => 100,
        //            '75%-99%'     => 60,
        '75%-84%' => 60,
        '85%-94%' => 60,
        '95%-99%' => 60,
        '100%' => 30,
        '100%_PUBLIC' => 30,
        'REPETITIONS' => 30,
        'INTERNAL' => 60,
        'MT' => 72,
        'ICE' => 0,
        'ICE_MT' => 72,
    ];

    protected static array $langPair2MTpayableRates = [
        "en" => [
            "it" => [
                'NO_MATCH' => 100,
                '50%-74%' => 100,
                //'75%-99%'     => 60,
                '75%-84%' => 60,
                '85%-94%' => 60,
                '95%-99%' => 60,
                '100%' => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL' => 60,
                'MT' => 62,
                'ICE' => 0,
                'ICE_MT' => 62,
            ],
            "de" => [
                'NO_MATCH' => 100,
                '50%-74%' => 100,
                //'75%-99%'     => 60,
                '75%-84%' => 60,
                '85%-94%' => 60,
                '95%-99%' => 60,
                '100%' => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL' => 60,
                'MT' => 67,
                'ICE' => 0,
                'ICE_MT' => 67,
            ],
            "fi" => [
                'NO_MATCH' => 100,
                '50%-74%' => 100,
                //'75%-99%'     => 60,
                '75%-84%' => 60,
                '85%-94%' => 60,
                '95%-99%' => 60,
                '100%' => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL' => 60,
                'MT' => 67,
                'ICE' => 0,
                'ICE_MT' => 67,
            ],
            "sr" => [
                'NO_MATCH' => 100,
                '50%-74%' => 100,
                //'75%-99%'     => 60,
                '75%-84%' => 60,
                '85%-94%' => 60,
                '95%-99%' => 60,
                '100%' => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL' => 60,
                'MT' => 67,
                'ICE' => 0,
                'ICE_MT' => 67,
            ],
            "ro" => [
                'NO_MATCH' => 100,
                '50%-74%' => 100,
                //'75%-99%'     => 60,
                '75%-84%' => 60,
                '85%-94%' => 60,
                '95%-99%' => 60,
                '100%' => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL' => 60,
                'MT' => 67,
                'ICE' => 0,
                'ICE_MT' => 67,
            ],
            "da" => [
                'NO_MATCH' => 100,
                '50%-74%' => 100,
                //'75%-99%'     => 60,
                '75%-84%' => 60,
                '85%-94%' => 60,
                '95%-99%' => 60,
                '100%' => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL' => 60,
                'MT' => 67,
                'ICE' => 0,
                'ICE_MT' => 67,
            ],
            "hu" => [
                'NO_MATCH' => 100,
                '50%-74%' => 100,
                //'75%-99%'     => 60,
                '75%-84%' => 60,
                '85%-94%' => 60,
                '95%-99%' => 60,
                '100%' => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL' => 60,
                'MT' => 67,
                'ICE' => 0,
                'ICE_MT' => 67,
            ],
            "id" => [
                'NO_MATCH' => 100,
                '50%-74%' => 100,
                //'75%-99%'     => 60,
                '75%-84%' => 60,
                '85%-94%' => 60,
                '95%-99%' => 60,
                '100%' => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL' => 60,
                'MT' => 67,
                'ICE' => 0,
                'ICE_MT' => 67,
            ],
            "sq" => [
                'NO_MATCH' => 100,
                '50%-74%' => 100,
                //'75%-99%'     => 60,
                '75%-84%' => 60,
                '85%-94%' => 60,
                '95%-99%' => 60,
                '100%' => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL' => 60,
                'MT' => 67,
                'ICE' => 0,
                'ICE_MT' => 67,
            ],
            "sv" => [
                'NO_MATCH' => 100,
                '50%-74%' => 100,
                //'75%-99%'     => 60,
                '75%-84%' => 60,
                '85%-94%' => 60,
                '95%-99%' => 60,
                '100%' => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL' => 60,
                'MT' => 62,
                'ICE' => 0,
                'ICE_MT' => 62,
            ],
            "is" => [
                'NO_MATCH' => 100,
                '50%-74%' => 100,
                //'75%-99%'     => 60,
                '75%-84%' => 60,
                '85%-94%' => 60,
                '95%-99%' => 60,
                '100%' => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL' => 60,
                'MT' => 67,
                'ICE' => 0,
                'ICE_MT' => 67,
            ],
            "ms" => [
                'NO_MATCH' => 100,
                '50%-74%' => 100,
                //'75%-99%'     => 60,
                '75%-84%' => 60,
                '85%-94%' => 60,
                '95%-99%' => 60,
                '100%' => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL' => 60,
                'MT' => 62,
                'ICE' => 0,
                'ICE_MT' => 62,
            ],
            "fr" => [
                'NO_MATCH' => 100,
                '50%-74%' => 100,
                //'75%-99%'     => 60,
                '75%-84%' => 60,
                '85%-94%' => 60,
                '95%-99%' => 60,
                '100%' => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL' => 60,
                'MT' => 67,
                'ICE' => 0,
                'ICE_MT' => 67,
            ],
            "pt" => [
                'NO_MATCH' => 100,
                '50%-74%' => 100,
                //'75%-99%'     => 60,
                '75%-84%' => 60,
                '85%-94%' => 60,
                '95%-99%' => 60,
                '100%' => 30,

                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL' => 60,
                'MT' => 62,
                'ICE' => 0,
                'ICE_MT' => 62,
            ],
            "es" => [
                'NO_MATCH' => 100,
                '50%-74%' => 100,
                //'75%-99%'     => 60,
                '75%-84%' => 60,
                '85%-94%' => 60,
                '95%-99%' => 60,
                '100%' => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL' => 60,
                'MT' => 67,
                'ICE' => 0,
                'ICE_MT' => 67,
            ],
            "nl" => [
                'NO_MATCH' => 100,
                '50%-74%' => 100,
                //'75%-99%'     => 60,
                '75%-84%' => 60,
                '85%-94%' => 60,
                '95%-99%' => 60,
                '100%' => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL' => 60,
                'MT' => 60,
                'ICE' => 0,
                'ICE_MT' => 60,
            ],
            "uk" => [
                'NO_MATCH' => 100,
                '50%-74%' => 100,
                //'75%-99%'     => 60,
                '75%-84%' => 60,
                '85%-94%' => 60,
                '95%-99%' => 60,
                '100%' => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL' => 60,
                'MT' => 77,
                'ICE' => 0,
                'ICE_MT' => 77,
            ],
            "hi" => [
                'NO_MATCH' => 100,
                '50%-74%' => 100,
                //'75%-99%'     => 60,
                '75%-84%' => 60,
                '85%-94%' => 60,
                '95%-99%' => 60,
                '100%' => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL' => 60,
                'MT' => 77,
                'ICE' => 0,
                'ICE_MT' => 77,
            ],
            "tl" => [
                'NO_MATCH' => 100,
                '50%-74%' => 100,
                //'75%-99%'     => 60,
                '75%-84%' => 60,
                '85%-94%' => 60,
                '95%-99%' => 60,
                '100%' => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL' => 60,
                'MT' => 77,
                'ICE' => 0,
                'ICE_MT' => 77,
            ],
            "ru" => [
                'NO_MATCH' => 100,
                '50%-74%' => 100,
                //'75%-99%'     => 60,
                '75%-84%' => 60,
                '85%-94%' => 60,
                '95%-99%' => 60,
                '100%' => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL' => 60,
                'MT' => 77,
                'ICE' => 0,
                'ICE_MT' => 77,
            ],
            "zh" => [
                'NO_MATCH' => 100,
                '50%-74%' => 100,
                //'75%-99%'     => 60,
                '75%-84%' => 60,
                '85%-94%' => 60,
                '95%-99%' => 60,
                '100%' => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL' => 60,
                'MT' => 77,
                'ICE' => 0,
                'ICE_MT' => 77,
            ],
            "zh-HK" => [
                'NO_MATCH' => 100,
                '50%-74%' => 100,
                //'75%-99%'     => 60,
                '75%-84%' => 60,
                '85%-94%' => 60,
                '95%-99%' => 60,
                '100%' => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL' => 60,
                'MT' => 90,
                'ICE' => 0,
                'ICE_MT' => 90,
            ],
            "ko" => [
                'NO_MATCH' => 100,
                '50%-74%' => 100,
                //'75%-99%'     => 60,
                '75%-84%' => 60,
                '85%-94%' => 60,
                '95%-99%' => 60,
                '100%' => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL' => 60,
                'MT' => 77,
                'ICE' => 0,
                'ICE_MT' => 77,
            ],
            "lt" => [
                'NO_MATCH' => 100,
                '50%-74%' => 100,
                //'75%-99%'     => 60,
                '75%-84%' => 60,
                '85%-94%' => 60,
                '95%-99%' => 60,
                '100%' => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL' => 60,
                'MT' => 77,
                'ICE' => 0,
                'ICE_MT' => 77,
            ],
            "ja" => [
                'NO_MATCH' => 100,
                '50%-74%' => 100,
                //'75%-99%'     => 60,
                '75%-84%' => 60,
                '85%-94%' => 60,
                '95%-99%' => 60,
                '100%' => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL' => 60,
                'MT' => 72,
                'ICE' => 0,
                'ICE_MT' => 72,
            ],
            "ga" => [
                'NO_MATCH' => 100,
                '50%-74%' => 100,
                //'75%-99%'     => 60,
                '75%-84%' => 60,
                '85%-94%' => 60,
                '95%-99%' => 60,
                '100%' => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL' => 60,
                'MT' => 77,
                'ICE' => 0,
                'ICE_MT' => 77,
            ],
            "km" => [
                'NO_MATCH' => 100,
                '50%-74%' => 100,
                //'75%-99%'     => 60,
                '75%-84%' => 60,
                '85%-94%' => 60,
                '95%-99%' => 60,
                '100%' => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL' => 60,
                'MT' => 72,
                'ICE' => 0,
                'ICE_MT' => 72,
            ],
            "xh" => [
                'NO_MATCH' => 100,
                '50%-74%' => 100,
                //'75%-99%'     => 60,
                '75%-84%' => 60,
                '85%-94%' => 60,
                '95%-99%' => 60,
                '100%' => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL' => 60,
                'MT' => 77,
                'ICE' => 0,
                'ICE_MT' => 77,
            ],
            "th" => [
                'NO_MATCH' => 100,
                '50%-74%' => 100,
                //'75%-99%'     => 60,
                '75%-84%' => 60,
                '85%-94%' => 60,
                '95%-99%' => 60,
                '100%' => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL' => 60,
                'MT' => 77,
                'ICE' => 0,
                'ICE_MT' => 77,
            ],
            "cs" => [
                'NO_MATCH' => 100,
                '50%-74%' => 100,
                //'75%-99%'     => 60,
                '75%-84%' => 60,
                '85%-94%' => 60,
                '95%-99%' => 60,
                '100%' => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL' => 60,
                'MT' => 77,
                'ICE' => 0,
                'ICE_MT' => 77,
            ],
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
    public static function getPayableRates(string $source, string $target): array
    {
        return self::resolveBreakdowns(static::$langPair2MTpayableRates, $source, $target);
    }

    /**
     * @param array $breakdowns
     * @param string $source
     * @param string $target
     * @param array|null $default
     *
     * @return array
     */
    public static function resolveBreakdowns(array $breakdowns, string $source, string $target, ?array $default = null): array
    {
        $languages = Languages::getInstance();
        $isoSource = $languages->convertLanguageToIsoCode($source);
        $isoTarget = $languages->convertLanguageToIsoCode($target);

        return array_merge(
            self::getBreakDown($breakdowns, $source, $target, $isoSource, $isoTarget),
            self::getBreakDown($breakdowns, $target, $source, $isoTarget, $isoSource)
        ) ?: ($default ?: static::$DEFAULT_PAYABLE_RATES);
    }

    protected static function getBreakDown(array $breakdowns, string $source, string $target, string $isoSource, string $isoTarget): array
    {
        if (isset($breakdowns[$source])) {
            if (isset($breakdowns[$source][$target])) {
                return $breakdowns[$source][$target];
            }

            if (isset($breakdowns[$source][$isoTarget])) {
                return $breakdowns[$source][$isoTarget];
            }
        }

        if (isset($breakdowns[$isoSource])) {
            if (isset($breakdowns[$isoSource][$target])) {
                return $breakdowns[$isoSource][$target];
            }

            if (isset($breakdowns[$isoSource][$isoTarget])) {
                return $breakdowns[$isoSource][$isoTarget];
            }
        }

        return [];
    }

}