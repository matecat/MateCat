<?php

class ProjectOptionsSanitizer {
    
    private $options ; 
    private $sanitized = array();  
    
    private $source_lang ; 
    private $target_lang ;

    private $boolean_keys = array('speech2text', 'lexiqa', 'tag_projection');

    public static $lexiQA_allowed_languages = array(
        'en-US',
        'en-GB',
        'fr-FR',
        'de-DE',
        'it-IT',
        'pt-PT',
        'pt-BR',
        'es-ES'
    );
    /**
     * All combinations of languages for Tag Ptojection
     */
    public static $tag_projection_allowed_languages = array(
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
        'de-it' => 'German - Italian',
        'de-fr' => 'German - French',
        'fr-it' => 'French - Italian',
        'fr-nl' => 'French - Dutch',
        'it-es' => 'Italian - Spanish',
        'nl-fi' => 'Dutch - Finnish',
        'da-sv' => 'Danish - Swedish',
        'nl-pt' => 'Dutch - Portuguese',
        'zh-en' => 'Chinese - English',
        'cs-de' => 'Czech - German',
    );



    public function __construct( $input_options ) { 
        $this->options = $input_options ; 
    }
    
    public function setLanguages( $source, $target ) {
        if ( !is_array( $target ) ) {
            $target = array( $target ) ; 
        }
        
        $this->source_lang = $source ; 
        $this->target_lang = $target ; 
    }

    /**
     * This method populates an array of sanitized input options. Known keys are sanitized.
     * Unknown keys are let as they are and copied to the sanitized array.
     *
     * @return array
     */
    public function sanitize() {
        $this->sanitized = $this->options ;

        if ( isset( $this->options['speech2text'] ) ) {
            $this->sanitizeSpeech2Text() ;
        }

        if( isset( $this->options['tag_projection'] ) ){
            $this->sanitizeTagProjection();
        }

        if( isset( $this->options['lexiqa'] ) ){
            $this->sanitizeLexiQA();
        }

        $this->sanitizeSegmentationRule();

        $this->convertBooleansToInt();

        return $this->sanitized ; 
    }

    private function convertBooleansToInt() {
        foreach($this->boolean_keys as $key) {
            if ( isset( $this->sanitized [ $key ] ) ) {
                $this->sanitized[ $key ] = (int) $this->sanitized[ $key ] ;
            }
        }
    }

    private function sanitizeSegmentationRule() {
        $rules = array( 'patent', 'paragraph' );

        if (
            array_key_exists('segmentation_rule', $this->options ) &&
            in_array( $this->options['segmentation_rule'], $rules )
        ) {
            $this->sanitized['segmentation_rule'] = $this->options['segmentation_rule'];
        }
        else {
            unset( $this->sanitized['segmentation_rule'] );
        }
    }

    // No special sanitization for speech2text required
    private function sanitizeSpeech2Text() {
        $this->sanitized['speech2text'] = !!$this->options['speech2text'] ;
    }

    /**
     * If Lexiqa is requested to be enabled, then check if language is in combination
     */
    private function sanitizeLexiQA() {
        if ( $this->options['lexiqa'] == TRUE && $this->checkSourceAndTargetAreInCombination( self::$lexiQA_allowed_languages ) ) {
            $this->sanitized['lexiqa'] = TRUE;
        } else {
            $this->sanitized['lexiqa'] = FALSE;
        }
    }

    /**
     * If tag project is requested to be enabled, check if language combination is allowed.
     */
    private function sanitizeTagProjection() {
        if ( $this->options['tag_projection'] == true && $this->checkSourceAndTargetAreInCombinationForTagProjection( self::$tag_projection_allowed_languages ) ) {
            $this->sanitized['tag_projection'] = TRUE;
        } else {
            $this->sanitized['tag_projection'] = FALSE;

        }
    }

    private function checkSourceAndTargetAreInCombination( $langs ) {
        $this->__ensureLanguagesAreSet();

        $all_langs = array_merge( $this->target_lang, array($this->source_lang) );

        $all_langs = array_unique( $all_langs ) ;

        $found = count( array_intersect( $langs, $all_langs ) ) ;
        return $found == 2 ;
    }

    private function checkSourceAndTargetAreInCombinationForTagProjection( $langs ) {
        $this->__ensureLanguagesAreSet();

        $lang_combination = array();
        $found = false;
        foreach ($this->target_lang as $value) {
            array_push($lang_combination, explode('-',$value)[0] . '-' . explode('-',$this->source_lang)[0]);
            array_push($lang_combination, explode('-',$this->source_lang)[0] . '-' . explode('-',$value)[0]);
        }

        foreach ($lang_combination as $langPair) {
            if (array_key_exists($langPair, $langs)) {
                $found = true;
                break;
            }
        }
        return $found ;
    }

    private function __ensureLanguagesAreSet() {
        if (is_null( $this->target_lang ) || is_null( $this->source_lang ) ) {
            throw  new Exception('Trying to sanitize options, but languages are not set') ;
        }
    }

}