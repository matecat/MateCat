<?php

namespace Utils\Constants;

use Utils\Engines\Altlang;
use Utils\Engines\Apertium;
use Utils\Engines\DeepL;
use Utils\Engines\GoogleTranslate;
use Utils\Engines\Intento;
use Utils\Engines\Lara;
use Utils\Engines\MicrosoftHub;
use Utils\Engines\MMT;
use Utils\Engines\MyMemory;
use Utils\Engines\SmartMATE;
use Utils\Engines\YandexTranslate;

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 23/02/15
 * Time: 15.08
 */
class EngineConstants
{

    const string MT   = "MT";
    const string TM   = "TM";
    const string NONE = "NONE";

    const MY_MEMORY        = 'Match';
    const APERTIUM         = 'Apertium';
    const ALTLANG          = 'Altlang';
    const SMART_MATE       = 'SmartMATE';
    const YANDEX_TRANSLATE = 'YandexTranslate';
    const MMT              = 'MMT';
    const LARA             = 'Lara';
    const DEEPL            = 'DeepL';
    const GOOGLE_TRANSLATE = 'GoogleTranslate';
    const INTENTO          = 'Intento';

    protected static array $ENGINES_LIST = [
            MyMemory::class        => MyMemory::class,
            Apertium::class        => Apertium::class,
            Altlang::class         => Altlang::class,
            SmartMATE::class       => SmartMATE::class,
            YandexTranslate::class => YandexTranslate::class,
            GoogleTranslate::class => GoogleTranslate::class,
            Intento::class         => Intento::class,
            MMT::class             => MMT::class,
            DeepL::class           => DeepL::class,
            Lara::class            => Lara::class, // new namespaced engine classes must be loaded by fully qualified class name
    ];

    /**
     * @return array
     */
    public static function getAvailableEnginesList(): array
    {
        return self::$ENGINES_LIST;
    }

}
