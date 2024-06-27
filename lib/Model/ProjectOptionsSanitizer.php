<?php

class ProjectOptionsSanitizer {

    private $options;
    private $sanitized = [];

    private $source_lang;
    private $target_lang;

    private $boolean_keys = [ 'speech2text', 'lexiqa', 'tag_projection' ];

    public static $lexiQA_allowed_languages = [
            'af-ZA',
            'sq-AL',
            'ar-SA',
            'hy-AM',
            'as-IN',
            'az-AZ',
            'fr-BE',
            'bn-IN',
            'be-BY',
            'bs-BA',
            'bg-BG',
            'my-MM',
            'ca-ES',
            'zh-CN',
            'zh-TW',
            'zh-HK',
            'hr-HR',
            'cs-CZ',
            'da-DK',
            'nl-NL',
            'en-GB',
            'en-US',
            'en-AU',
            'en-CA',
            'en-IN',
            'en-IE',
            'en-NZ',
            'en-SG',
            'et-EE',
            'fi-FI',
            'nl-BE',
            'fr-FR',
            'fr-CA',
            'fr-CH',
            'de-DE',
            'ka-GE',
            'el-GR',
            'gu-IN',
            'hi-IN',
            'hu-HU',
            'id-ID',
            'it-IT',
            'ja-JP',
            'jv-ID',
            'ha-NG',
            'he-IL',
            'ht-HT',
            'kk-KZ',
            'rn-BI',
            'ko-KR',
            'ky-KG',
            'lv-LV',
            'lt-LT',
            'mk-MK',
            'ms-MY',
            'mr-IN',
            'ne-NP',
            'nb-NO',
            'fa-IR',
            'pl-PL',
            'pt-PT',
            'pt-BR',
            'ro-RO',
            'ru-RU',
            'sr-Latn-RS',
            'sr-Cyrl-RS',
            'si-LK',
            'sk-SK',
            'sl-SI',
            'es-ES',
            'es-CO',
            'es-MX',
            'es-US',
            'es-419',
            'sw-KE',
            'sv-SE',
            'de-CH',
            'tl-PH',
            'ta-LK',
            'ta-IN',
            'th-TH',
            'tr-TR',
            'uk-UA',
            'ur-PK',
            'uz-UZ',
            'vi-VN'

    ];
    /**
     * All combinations of languages for Tag Ptojection
     */
    public static $tag_projection_allowed_languages = [
            'en-de' => 'English - German',
            'en-es' => 'English - Spanish',
            'en-fr' => 'English - French',
            'en-it' => 'English - Italian',
            'en-pt' => 'English - Portuguese',
            'en-ru' => 'English - Russian',
            'en-cs' => 'English - Czech',
            'en-nl' => 'English - Dutch',
            'en-fi' => 'English - Finnish',
            'en-pl' => 'English - Polish',
            'en-da' => 'English - Danish',
            'en-sv' => 'English - Swedish',
            'en-el' => 'English - Greek',
            'en-hu' => 'English - Hungarian',
            'en-lt' => 'English - Lithuanian',
            'en-ja' => 'English - Japanese',
            'en-et' => 'English - Estonian',
            'en-sk' => 'English - Slovak',
            'en-bg' => 'English - Bulgarian',
            'en-bs' => 'English - Bosnian',
            'en-ar' => 'English - Arabic',
            'en-ca' => 'English - Catalan',
            'en-zh' => 'English - Chinese',
            'en-he' => 'English - Hebrew',
            'en-hr' => 'English - Croatian',
            'en-id' => 'English - Indonesian',
            'en-is' => 'English - Icelandic',
            'en-ko' => 'English - Korean',
            'en-lv' => 'English - Latvian',
            'en-mk' => 'English - Macedonian',
            'en-ms' => 'English - Malay',
            'en-mt' => 'English - Maltese',
            'en-nb' => 'English - Norwegian Bokmål',
            'en-nn' => 'English - Norwegian Nynorsk',
            'en-ro' => 'English - Romanian',
            'en-sl' => 'English - Slovenian',
            'en-sq' => 'English - Albanian',
            'en-sr' => 'English - Montenegrin',
            'en-th' => 'English - Thai',
            'en-tr' => 'English - Turkish',
            'en-uk' => 'English - Ukrainian',
            'en-vi' => 'English - Vietnamese',
            'de-it' => 'German - Italian',
            'de-fr' => 'German - French',
            'de-cs' => 'German - Czech',
            'fr-it' => 'French - Italian',
            'fr-nl' => 'French - Dutch',
            'it-es' => 'Italian - Spanish',
            'da-sv' => 'Danish - Swedish',
            'nl-pt' => 'Dutch - Portuguese',
            'nl-fi' => 'Dutch - Finnish',
            'zh-en' => 'Chinese - English',
            'sv-da' => 'Swedish - Danish',
            'cs-de' => 'Czech - German',
    ];


    public function __construct( $input_options ) {
        $this->options = $input_options;
    }

    /**
     * @param $source
     * @param $target
     */
    public function setLanguages( $source, $target ) {
        if ( is_string( $target ) ) {
            $target = [ $target ];
        } elseif ( method_exists( $target, 'getArrayCopy' ) ) {
            $target = $target->getArrayCopy();
        }

        if ( !is_array( $target ) ) {
            throw new Exception( 'Target should be an array' );
        }

        $this->source_lang = $source;
        $this->target_lang = $target;
    }

    /**
     * This method populates an array of sanitized input options. Known keys are sanitized.
     * Unknown keys are let as they are and copied to the sanitized array.
     *
     * @return array
     */
    public function sanitize() {
        $this->sanitized = $this->options;

        if ( isset( $this->options[ 'speech2text' ] ) ) {
            $this->sanitizeSpeech2Text();
        }

        if ( isset( $this->options[ 'tag_projection' ] ) ) {
            $this->sanitizeTagProjection();
        }

        if ( isset( $this->options[ 'lexiqa' ] ) ) {
            $this->sanitizeLexiQA();
        }

        $this->sanitizeSegmentationRule();

        $this->convertBooleansToInt();

        return $this->sanitized;
    }

    private function convertBooleansToInt() {
        foreach ( $this->boolean_keys as $key ) {
            if ( isset( $this->sanitized [ $key ] ) ) {
                $this->sanitized[ $key ] = (int)$this->sanitized[ $key ];
            }
        }
    }

    private function sanitizeSegmentationRule() {
        $rules = [ 'patent', 'paragraph' ];

        if (
                isset( $this->options[ 'segmentation_rule' ] ) &&
                in_array( $this->options[ 'segmentation_rule' ], $rules )
        ) {
            $this->sanitized[ 'segmentation_rule' ] = $this->options[ 'segmentation_rule' ];
        } else {
            unset( $this->sanitized[ 'segmentation_rule' ] );
        }
    }

    // No special sanitization for speech2text required
    private function sanitizeSpeech2Text() {
        $this->sanitized[ 'speech2text' ] = !!$this->options[ 'speech2text' ];
    }

    /**
     * If Lexiqa is requested to be enabled, then check if language is in combination
     */
    private function sanitizeLexiQA() {
        $this->sanitized[ 'lexiqa' ] = ( $this->options[ 'lexiqa' ] == true and $this->checkSourceAndTargetAreInCombination( self::$lexiQA_allowed_languages ) );
    }

    /**
     * If tag project is requested to be enabled, check if language combination is allowed.
     */
    private function sanitizeTagProjection() {
        $this->sanitized[ 'tag_projection' ] = ( $this->options[ 'tag_projection' ] == true and $this->checkSourceAndTargetAreInCombinationForTagProjection( self::$tag_projection_allowed_languages ) );
    }

    /**
     * @param array $langs
     *
     * @return bool
     * @throws Exception
     */
    private function checkSourceAndTargetAreInCombination( $langs ) {
        $this->__ensureLanguagesAreSet();

        $all_langs = array_merge( $this->target_lang, [ $this->source_lang ] );
        $all_langs = array_unique( $all_langs );
        $found = count( array_intersect( $langs, $all_langs ) );

        return $found >= 2;
    }

    private function checkSourceAndTargetAreInCombinationForTagProjection( $langs ) {
        $this->__ensureLanguagesAreSet();

        $lang_combination = [];
        $found            = false;
        foreach ( $this->target_lang as $value ) {
            array_push( $lang_combination, explode( '-', $value )[ 0 ] . '-' . explode( '-', $this->source_lang )[ 0 ] );
            array_push( $lang_combination, explode( '-', $this->source_lang )[ 0 ] . '-' . explode( '-', $value )[ 0 ] );
        }

        foreach ( $lang_combination as $langPair ) {
            if ( array_key_exists( $langPair, $langs ) ) {
                $found = true;
                break;
            }
        }
        return $found;
    }

    private function __ensureLanguagesAreSet() {
        if ( is_null( $this->target_lang ) || is_null( $this->source_lang ) ) {
            throw  new Exception( 'Trying to sanitize options, but languages are not set' );
        }
    }

}