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
    const MICROSOFT_HUB    = 'MicrosoftHub';
    const APERTIUM         = 'Apertium';
    const ALTLANG	       = 'Altlang';
    const SMART_MATE       = 'SmartMATE';
    const YANDEX_TRANSLATE = 'YandexTranslate';
    const MMT              = 'MMT';
    const DEEPL            = 'DeepL';
    const GOOGLE_TRANSLATE = 'GoogleTranslate';
    const INTENTO          = 'Intento';

    protected static $ENGINES_LIST = [
            self::MY_MEMORY        => self::MY_MEMORY,
            self::MICROSOFT_HUB    => self::MICROSOFT_HUB,
            self::APERTIUM         => self::APERTIUM,
            self::ALTLANG          => self::ALTLANG,
            self::SMART_MATE       => self::SMART_MATE,
            self::YANDEX_TRANSLATE => self::YANDEX_TRANSLATE,
            self::GOOGLE_TRANSLATE => self::GOOGLE_TRANSLATE,
            self::INTENTO          => self::INTENTO,
            self::MMT              => self::MMT,
            self::DEEPL            => self::DEEPL,
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
