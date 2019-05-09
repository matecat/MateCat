<?php

/*
   this class manages supported languages in the CAT tool
 */

class Langs_LanguageDomains {

    private static $instance; //singleton instance
    private static $subjectMap;

    //access singleton
    public static function getInstance() {
        if ( !self::$instance ) {
            self::$instance = new Langs_LanguageDomains();
        }

        return self::$instance;
    }

    //constructor
    private function __construct() {
        //get languages file
        //
        // SDL supported language codes
        // http://kb.sdl.com/kb/?ArticleId=2993&source=Article&c=12&cid=23#tab:homeTab:crumb:7:artId:4878

        $file = INIT::$UTILS_ROOT . '/Langs/languageDomains.json';

        $string = file_get_contents( $file );
        //parse to associative array
        $subjects = json_decode( $string, true );
        Utils::raiseJsonExceptionError();

        self::$subjectMap = $subjects;
    }

    //get list of languages, as RFC3066
    public static function getEnabledDomains() {
        return self::$subjectMap;
    }

    public static function getDisplayDomain( $domainID ) {
        foreach ( self::$subjectMap as $domain ) {
            if ( $domain[ 'key' ] == $domainID ) {
                return $domain[ 'display' ];
            }
        }

        return "Generic";
    }
}