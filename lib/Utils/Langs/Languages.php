<?php

/*
   this class manages supported languages in the CAT tool
 */

class Langs_Languages {

    private static $instance; //singleton instance
    private static $map_string2rfc; //associative map on language names -> codes
    private static $map_rfc2obj; //internal support map rfc -> language data
    private static $map_iso2rfc; //associative map iso -> rfc codes

    //map that associates a language pair (in iso format) to a payable rate
    private static $langPair2MTpayableRate;


    //access singleton
    public static function getInstance() {
        if ( !self::$instance ) {
            self::$instance = new Langs_Languages();
        }

        return self::$instance;
    }

    //constructor
    private function __construct() {
        //get languages file
        //
        // SDL supported language codes
        // http://kb.sdl.com/kb/?ArticleId=2993&source=Article&c=12&cid=23#tab:homeTab:crumb:7:artId:4878

        $file = INIT::$UTILS_ROOT . '/Langs/supported_langs.json';
        if ( !file_exists( $file ) ) {
            log::doLog( "no language defs found in $file" );
            exit;
        }
        $string = file_get_contents( $file );

        //parse to associative array
        $langs = json_decode( $string, true );
        $langs = $langs[ 'langs' ];

        //build internal maps
        //for each lang
        foreach ( $langs as $k1 => $lang ) {

            //for each localization of that lang
            foreach ( $lang[ 'localized' ] as $k2 => $localizedTagPair ) {
                foreach ( $localizedTagPair as $isocode => $localizedTag ) {

                    //build mapping of localized string -> rfc code
                    self::$map_string2rfc[ $localizedTag ] = $lang[ 'rfc3066code' ];

                    //add associative reference
                    $langs[ $k1 ][ 'localized' ][ $isocode ] = $localizedTag;
                }

                //remove positional reference
                unset( $langs[ $k1 ][ 'localized' ][ $k2 ] );
            }
        }

        //create internal support objects representation
        foreach ( $langs as $lang ) {

            //add code -> rfc mapping
            if ( isset( $lang[ 'languageRegionCode' ] ) ) {
                self::$map_string2rfc[ $lang[ 'languageRegionCode' ] ] = $lang[ 'rfc3066code' ];
            }

            //add rfc fallback
            self::$map_string2rfc[ $lang[ 'rfc3066code' ] ] = $lang[ 'rfc3066code' ];

            //primary pointers are RFC
            self::$map_rfc2obj[ $lang[ 'rfc3066code' ] ] = $lang;

            //set support for ISO by indirect reference through RFC pointers
            self::$map_iso2rfc[ $lang[ 'isocode' ] ] = $lang[ 'rfc3066code' ];

            //manage ambiguities
            self::$map_iso2rfc[ 'en' ] = 'en-US';
            self::$map_iso2rfc[ 'pt' ] = 'pt-BR';
        }

    }

//get localized name of a language
    public function getLocalizedName( $code, $lang = 'en' ) {
        //convert ISO code in RFC
        if ( strlen( $code ) < 5 ) {
            $code = self::$map_iso2rfc[ $code ];
        }

        return self::$map_rfc2obj[ $code ][ 'localized' ][ $lang ];
    }

//is right-to-left language?
    public static function isRTL( $code ) {
        //convert ISO code in RFC
        if ( strlen( $code ) < 5 ) {
            $code = self::$map_iso2rfc[ $code ];
        }

        return self::$map_rfc2obj[ $code ][ 'rtl' ];
    }

    //is language enabled
    public static function isEnabled( $code ) {
        //convert ISO code in RFC
        if ( strlen( $code ) < 5 ) {
            $code = self::$map_iso2rfc[ $code ];
        }

        return self::$map_rfc2obj[ $code ][ 'enabled' ];
    }

    /**
     * get corresponding Language-Region code given a localized name
     * http://www.rfc-editor.org/rfc/rfc5646.txt
     * http://www.w3.org/International/articles/language-tags/
     */
    public static function getLangRegionCode( $localizedName ) {
        @$value = self::$map_rfc2obj[ self::$map_string2rfc[ $localizedName ] ][ 'languageRegionCode' ];
        if ( empty( $value ) ) {
            $value = self::get3066Code( $localizedName );
        }

        return $value;
    }

    //get corresponding RFC3066 code given a localized name
    public static function get3066Code( $localizedName ) {
        return self::$map_string2rfc[ $localizedName ];
    }

    //get corresponding ISO 639-1 code given a localized name
    public static function getIsoCode( $localizedName ) {
        return self::$map_rfc2obj[ self::$map_string2rfc[ $localizedName ] ][ 'isocode' ];
    }

    //get list of languages, as RFC3066
    public static function getEnabledLanguages( $localizationLang = 'en' ) {

        foreach ( self::$map_rfc2obj as $rfc => $lang ) {
            //if marked as enabled, add to result
            if ( $lang[ 'enabled' ] ) {
                $code    = $rfc;
                $top     = isset( $lang[ 'top' ] ) ? $lang[ 'top' ] : '';
                $list[ ] = array( 'code' => $code, 'name' => $lang[ 'localized' ][ $localizationLang ], 'top' => $top, 'direction' => ( $lang['rtl'] ) ? 'rtl' : 'ltr' );
            }
        }

        return $list;
    }


}

?>
