<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 23/02/15
 * Time: 15.08
 */
class Constants_Engines {

    const MT   = "MT";
    const TM   = "TM";
    const NONE = "NONE";

    const MY_MEMORY        = 'MyMemory';
    const MOSES            = 'Moses';
    const TAUYOU	       = 'Tauyou';
    const MICROSOFT_HUB    = 'MicrosoftHub';
    const IP_TRANSLATOR    = 'IPTranslator';
    const APERTIUM         = 'Apertium';
    const ALTLANG	       = 'Altlang';
    const LETSMT           = 'LetsMT';
    const SMART_MATE       = 'SmartMATE';
    const YANDEX_TRANSLATE = 'YandexTranslate';
    const MMT              = 'MMT';
    const GOOGLE_TRANSLATE = 'GoogleTranslate';
    const MTHUB            = 'MTHUB';
    const INTENTO          = 'Intento';

    protected static $ENGINES_LIST = [
            self::MY_MEMORY        => self::MY_MEMORY,
            self::MOSES            => self::MOSES,
            self::TAUYOU           => self::TAUYOU,
            self::MICROSOFT_HUB    => self::MICROSOFT_HUB,
            self::IP_TRANSLATOR    => self::IP_TRANSLATOR,
            self::APERTIUM         => self::APERTIUM,
            self::ALTLANG          => self::ALTLANG,
            self::LETSMT           => self::LETSMT,
            self::SMART_MATE       => self::SMART_MATE,
            self::YANDEX_TRANSLATE => self::YANDEX_TRANSLATE,
            self::GOOGLE_TRANSLATE => self::GOOGLE_TRANSLATE,
            self::MTHUB            => self::MTHUB,
            self::INTENTO          => self::INTENTO,
            self::MMT              => self::MMT,
    ];

    /**
     * @return array
     */
    public static function getAvailableEnginesList(){
        return self::$ENGINES_LIST;
    }

    public static function setInEnginesList( $engine ){
        if( defined( 'self::' . $engine ) ){
            self::$ENGINES_LIST[ $engine ] = $engine;
        }
    }

}
