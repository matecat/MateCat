<?php

namespace Utils\Langs;

use Utils\Logger\Log;
use Utils\Registry\AppConfig;

class Languages {

    private static ?Languages $instance       = null; //singleton instance
    private static array      $map_string2rfc = []; //associative map on language names -> codes
    private static array      $map_rfc2obj    = []; //internal support map rfc -> language data

    /*
     * Associative map iso → rfc codes.
     *
     * IMPORTANT:
     *
     * This map is an approximation and should not be used to resolve specific language variants.
     * For example, the english has more than 6 variants (ex: en -> en-??)
     *
     * This map should be used only to allow internal code to work even for general language codes.
     *
     */
    private static array $map_iso2rfc           = [];
    private static array $languages_definition  = []; // the complete json struct
    private static array $ocr_supported         = [];
    private static array $ocr_notSupported      = [];
    private static array $enabled_language_list = [];

    /**
     * Languages constructor.
     */
    private function __construct() {
        //get languages file
        //
        // SDL supported language codes
        // http://kb.sdl.com/kb/?ArticleId=2993&source=Article&c=12&cid=23#tab:homeTab:crumb:7:artId:4878

        $file = AppConfig::$UTILS_ROOT . '/Langs/supported_langs.json';
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

                    // ocr support
                    if ( $lang[ 'ocr' ][ 'supported' ] === true ) {
                        self::$ocr_supported[ $localizedTag ] = $lang[ 'rfc3066code' ];
                    }

                    if ( $lang[ 'ocr' ][ 'not_supported_or_rtl' ] === true ) {
                        self::$ocr_notSupported[ $localizedTag ] = $lang[ 'rfc3066code' ];
                    }
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

            //manage ambiguities (approximation)
            self::$map_iso2rfc[ 'en' ] = 'en-US';
            self::$map_iso2rfc[ 'sp' ] = 'sp-SP';
            self::$map_iso2rfc[ 'pt' ] = 'pt-PT';
            self::$map_iso2rfc[ 'fr' ] = 'fr-FR';
            self::$map_iso2rfc[ 'ar' ] = 'ar-SA';
            self::$map_iso2rfc[ 'zh' ] = 'zh-CN';
            self::$map_iso2rfc[ 'it' ] = 'it-IT';

        }

        foreach ( self::$map_rfc2obj as $rfc => $lang ) {
            //if marked as enabled, add to result
            if ( $lang[ 'enabled' ] ) {
                self::$enabled_language_list[ $rfc ] = [
                        'code'      => $rfc,
                        'name'      => $lang[ 'localized' ][ 'en' ],
                        'direction' => ( $lang[ 'rtl' ] ) ? 'rtl' : 'ltr'
                ];
            }
        }

        uasort( self::$enabled_language_list, function ( $a, $b ) {
            return strcmp( $a[ 'name' ], $b[ 'name' ] );
        } );

    }

    /**
     * @return Languages
     */
    public static function getInstance(): Languages {
        if ( !self::$instance ) {
            self::$instance = new Languages();
        }

        return self::$instance;
    }

    /**
     * Check if a language is RTL
     *
     * @param string $code
     *
     * @return bool
     */
    public static function isRTL( string $code ): bool {
        //convert ISO code in RFC
        $code = self::getInstance()->normalizeLanguageCode( $code );

        return self::$map_rfc2obj[ $code ][ 'rtl' ];
    }

    /**
     * Check if the language is enabled
     *
     * @param string $code
     *
     * @return mixed
     */
    public function isEnabled( string $code ) {
        //convert ISO code in RFC
        $code = $this->normalizeLanguageCode( $code );

        return self::$map_rfc2obj[ $code ][ 'enabled' ];
    }

    /**
     * get the corresponding Language-Region code given the localized name
     * http://www.rfc-editor.org/rfc/rfc5646.txt
     * http://www.w3.org/International/articles/language-tags/
     */
    public function getLangRegionCode( $localizedName ) {
        $value = self::$map_rfc2obj[ self::$map_string2rfc[ $localizedName ] ][ 'languageRegionCode' ] ?? null;
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
     *
     * @param $localizedName
     *
     * @return mixed
     */
    public function getIsoCode( $localizedName ) {
        return self::$map_rfc2obj[ self::$map_string2rfc[ $localizedName ] ][ 'isocode' ];
    }

    /**
     * get a list of enabled languages ordered by name alphabetically, the keys are the language rfc3066code codes
     *
     * @return array
     */
    public function getEnabledLanguages(): array {
        return self::$enabled_language_list;
    }

    /**
     *
     * Get the corresponding ISO 639-1 code given a localized name
     *
     * @param             $code
     * @param string|null $lang
     *
     * @return mixed
     */
    public function getLocalizedName( $code, ?string $lang = 'en' ) {
        $code = $this->normalizeLanguageCode( $code );

        return self::$map_rfc2obj[ $code ][ 'localized' ][ $lang ];
    }

    /**
     *
     * Be strict when and only find localized name with an RFC expected input
     *
     * @param             $code
     * @param string|null $lang
     *
     * @return mixed
     * @throws InvalidLanguageException
     */
    public function getLocalizedNameRFC( $code, ?string $lang = 'en' ) {
        if ( !array_key_exists( $code, self::$map_rfc2obj ) ) {
            throw new InvalidLanguageException( 'Invalid language code: ' . $code );
        }

        return self::$map_rfc2obj[ $code ][ 'localized' ][ $lang ];
    }

    /**
     * Returns a list of RTL language codes
     *
     * @return array
     */
    public function getRTLLangs() {
        $acc = [];
        foreach ( self::$map_rfc2obj as $code => $value ) {
            if ( $value[ 'rtl' ] && $value[ 'enabled' ] ) {
                $acc[] = $code;
            }
        }

        return $acc;
    }

    /**
     * @throws InvalidLanguageException
     */
    public function validateLanguage( ?string $code = null ): string {

        if ( empty( $code ) ) {
            throw new InvalidLanguageException( "Missing language.", -3 );
        }

        $code = $this->normalizeLanguageCode( $code );

        $this->getLocalizedNameRFC( $code );
        if ( !$this->isEnabled( $code ) ) {
            throw new InvalidLanguageException( 'Language not enabled: ' . $code );
        }

        return $code;

    }

    /**
     * @throws InvalidLanguageException
     */
    public function validateLanguageList( array $languageList ): array {

        if ( empty( $languageList ) ) {
            throw new InvalidLanguageException( "Empty language list.", -3 );
        }

        $langList = [];
        foreach ( $languageList as $language ) {
            $langList[] = $this->validateLanguage( $language );
        }

        return $langList;

    }

    /**
     * @throws InvalidLanguageException
     */
    public function validateLanguageListAsString( string $languageList, ?string $separator = ',' ): string {

        $targets = explode( $separator, $languageList );
        $targets = array_map( 'trim', $targets );
        $targets = array_unique( $targets );

        return implode( ',', $this->validateLanguageList( $targets ) );
    }

    /**
     * @param string $languageCode
     *
     * @return string|null
     */
    protected function normalizeLanguageCode( string $languageCode ): ?string {

        $langParts = explode( '-', $languageCode );

        $langParts[ 0 ] = trim( strtolower( $langParts[ 0 ] ) );

        if ( sizeof( $langParts ) == 1 ) {
            /*
             *  IMPORTANT: Pick the first language region. This is an approximation. Use this only to normalize the language code.
             */
            return self::$map_iso2rfc[ $langParts[ 0 ] ] ?? null;
        } elseif ( sizeof( $langParts ) == 2 ) {
            $langParts[ 1 ] = trim( strtoupper( $langParts[ 1 ] ) );
        } elseif ( sizeof( $langParts ) == 3 ) {
            $langParts[ 1 ] = ucfirst( trim( strtolower( $langParts[ 1 ] ) ) );
            $langParts[ 2 ] = trim( strtoupper( $langParts[ 2 ] ) );
        } else {
            return null;
        }

        return implode( "-", $langParts );

    }

    /**
     * @param string $language
     *
     * @return bool
     */
    public static function isValidLanguage( string $language ): bool {
        $language = self::getInstance()->normalizeLanguageCode( $language );

        return array_key_exists( $language, self::$map_rfc2obj );
    }

    /**
     * @param string $rfc3066code
     *
     * @return string|null
     */
    public static function getLocalizedLanguage( string $rfc3066code ): ?string {
        foreach ( self::$languages_definition as $lang ) {
            if ( $lang[ 'rfc3066code' ] === $rfc3066code ) {
                return $lang[ 'localized' ][ 'en' ] ?? null;
            }
        }

        return null;
    }

    /**
     * Examples:
     *
     * it-IT  ---> it
     * es-419 ---> es
     * to-TO ----> ton
     *
     * @param string $code
     *
     * @return string|null
     */
    public static function convertLanguageToIsoCode( string $code ): ?string {
        $code = self::getInstance()->normalizeLanguageCode( $code );

        return self::$map_rfc2obj[ $code ][ 'isocode' ] ?? null;
    }

    /**
     * @return array
     */
    public static function getLanguagesWithOcrSupported(): array {
        return self::$ocr_supported;
    }

    /**
     * @return array
     */
    public static function getLanguagesWithOcrNotSupported(): array {
        return self::$ocr_notSupported;
    }
}

