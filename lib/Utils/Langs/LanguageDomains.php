<?php

namespace Utils\Langs;
/*
   this class manages supported languages in the CAT tool
 */

use Exception;
use Utils\Registry\AppConfig;

class LanguageDomains {

    private static ?LanguageDomains $instance = null; //singleton instance
    private static array           $subjectMap;
    private static array           $subjectHashMap = [];

    //access singleton
    public static function getInstance(): LanguageDomains {
        if ( !self::$instance ) {
            self::$instance = new LanguageDomains();
        }

        return self::$instance;
    }

    /**
     * @throws Exception
     */
    private function __construct() {
        //get languages file
        //
        // SDL supported language codes
        // http://kb.sdl.com/kb/?ArticleId=2993&source=Article&c=12&cid=23#tab:homeTab:crumb:7:artId:4878

        $file = AppConfig::$UTILS_ROOT . '/Langs/languageDomains.json';

        $string = file_get_contents( $file );
        //parse to an associative array
        $subjects = json_decode( $string, true, 512, JSON_THROW_ON_ERROR );

        self::$subjectMap = $subjects;

        array_walk( self::$subjectMap, function ( $element ) {
            self::$subjectHashMap[ $element[ 'key' ] ] = $element[ 'display' ];
        } );

    }

    //get list of languages, as RFC3066
    public static function getEnabledDomains() {
        return self::$subjectMap;
    }

    /**
     * @return array
     */
    public static function getEnabledHashMap(): array {
        return self::$subjectHashMap;
    }

}