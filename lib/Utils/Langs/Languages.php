<?php

/*
   this class manages supported languages in the CAT tool
 */


class Lang_InvalidLanguageException extends Exception {} ;

class Langs_Languages {

    private static $instance; //singleton instance
    private static $map_string2rfc; //associative map on language names -> codes
    private static $map_rfc2obj; //internal support map rfc -> language data
    private static $map_iso2rfc; //associative map iso -> rfc codes
    private static $languages_definition; // the complete json struct

    /**
     * Langs_Languages constructor.
     */
    private function __construct() {
        //get languages file
        //
        // SDL supported language codes
        // http://kb.sdl.com/kb/?ArticleId=2993&source=Article&c=12&cid=23#tab:homeTab:crumb:7:artId:4878

        $file = INIT::$UTILS_ROOT . '/Langs/supported_langs.json';
        if ( !file_exists( $file ) ) {
            Log::doJsonLog( "no language defs found in $file" );
            exit;
        }
        $string = file_get_contents( $file );

        //parse to associative array
        $langs                      = json_decode( $string, true );
        self::$languages_definition = $langs[ 'langs' ];

        //build internal maps
        //for each lang
        foreach ( self::$languages_definition as $k1 => $lang ) {

            //for each localization of that lang
            foreach ( $lang[ 'localized' ] as $k2 => $localizedTagPair ) {
                foreach ( $localizedTagPair as $isocode => $localizedTag ) {

                    //build mapping of localized string -> rfc code
                    self::$map_string2rfc[ $localizedTag ] = $lang[ 'rfc3066code' ];

                    //add associative reference
                    self::$languages_definition[ $k1 ][ 'localized' ][ $isocode ] = $localizedTag;
                }

                //remove positional reference
                unset( self::$languages_definition[ $k1 ][ 'localized' ][ $k2 ] );
            }
        }

        //create internal support objects representation
        foreach ( self::$languages_definition as $lang ) {

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

    /**
     * @return Langs_Languages
     */
    public static function getInstance() {
        if ( !self::$instance ) {
            self::$instance = new Langs_Languages();
        }

        return self::$instance;
    }

    /**
     * Check if a language is RTL
     *
     * @param $code
     *
     * @return mixed
     */
    public static function isRTL( $code ) {
        //convert ISO code in RFC
        if ( strlen( $code ) < 5 ) {
            $code = self::$map_iso2rfc[ $code ];
        }

        return self::$map_rfc2obj[ $code ][ 'rtl' ];
    }

    /**
     * Check if the language is enabled
     * @param $code
     *
     * @return mixed
     */
    public function isEnabled( $code ) {
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
    public function getLangRegionCode( $localizedName ) {
        @$value = self::$map_rfc2obj[ self::$map_string2rfc[ $localizedName ] ][ 'languageRegionCode' ];
        if ( empty( $value ) ) {
            $value = $this->get3066Code( $localizedName );
        }

        return $value;
    }

    /**
     * get list of languages, as RFC3066
     *
     * @param $localizedName
     *
     * @return mixed
     */
    public function get3066Code( $localizedName ) {
        return self::$map_string2rfc[ $localizedName ];
    }

    /**
     * get list of languages, as ISO Code
     * @param $localizedName
     *
     * @return mixed
     */
    public function getIsoCode( $localizedName ) {
        return self::$map_rfc2obj[ self::$map_string2rfc[ $localizedName ] ][ 'isocode' ];
    }

    /**
     * get list of enabled languages
     *
     * @param string $localizationLang
     *
     * @return array
     */
    public function getEnabledLanguages( $localizationLang = 'en' ) {

        foreach ( self::$map_rfc2obj as $rfc => $lang ) {
            //if marked as enabled, add to result
            if ( $lang[ 'enabled' ] ) {
                $code   = $rfc;
                $list[] = array(
                        'code'      => $code,
                        'name'      => $lang[ 'localized' ][ $localizationLang ],
                        'direction' => ( $lang[ 'rtl' ] ) ? 'rtl' : 'ltr'
                );
            }
        }

        return $list;
    }

    /**
     * 
     * Get corresponding ISO 639-1 code given a localized name
     *
     * @param        $code
     * @param string $lang
     *
     * @throws Exception
     *
     * @return mixed
     */
    public function getLocalizedName( $code, $lang = 'en' ) {
        if ( strlen( $code ) < 5 ) {
            $code = self::$map_iso2rfc[ $code ];
        }
        return self::$map_rfc2obj[ $code ][ 'localized' ][ $lang ];
    }

    /**
     *
     * Be strict when and only find localized name with an RFC expected input
     * 
     * @param        $code
     * @param string $lang
     *
     * @return mixed
     * @throws Exception
     */
    public function getLocalizedNameRFC( $code, $lang = 'en') {
        if ( !array_key_exists( $code, self::$map_rfc2obj ) ) {
            throw new Lang_InvalidLanguageException('Invalid language code: ' . $code ) ;
        }
        return self::$map_rfc2obj[ $code ][ 'localized' ][ $lang ];
    }

    /**
     * Returns a list of RTL language codes
     *
     * @return array
     */
    public function getRTLLangs() {
        $acc = array();
        foreach ( self::$map_rfc2obj as $code => $value ) {
            if ( $value[ 'rtl' ] && $value[ 'enabled' ] ) {
                $acc[] = $code;
            }
        }

        return $acc;
    }

    public function validateLanguage( $code = null ){
        if ( empty( $code ) ) {
            throw new Lang_InvalidLanguageException( "Missing language.", -3 );
        }
        $this->getLocalizedNameRFC( $code ) ;
        if( !$this->isEnabled( $code ) ) throw new Lang_InvalidLanguageException( 'Language not enabled: ' . $code );
    }

}

