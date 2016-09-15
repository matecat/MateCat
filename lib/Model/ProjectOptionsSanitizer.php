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
        'it-IT'
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
        'de-it' => 'German - Italian',
        'de-fr' => 'German - French',
        'fr-it' => 'French - Italian',
        'it-es' => 'Italian - Spanish'
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
    
    public function sanitize() {
        $this->sanitized = array(); 
        
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
        $rules = array( 'patent' );
        if ( array_search( @$this->options[ 'segmentation_rule' ], $rules ) !== false ) {
            $this->sanitized[ 'segmentation_rule' ] = $this->options[ 'segmentation_rule' ];
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
        if ( $this->options['tag_projection'] == true && $this->checkSourceAndTargetAreInCombination( self::$tag_projection_allowed_languages ) ) {
            $this->sanitized['tag_projection'] = TRUE;
        } else {
            $this->sanitized['tag_projection'] = FALSE;

        }
    }
    
    private function checkSourceAndTargetAreInCombination( $langs ) {
        $lang_combination = array();
        foreach ($this->target_lang as &$value) {
            array_push($lang_combination, explode('-',$value)[0] . '-' . explode('-',$this->source_lang)[0]);
            array_push($lang_combination, explode('-',$this->source_lang)[0] . '-' . explode('-',$value)[0]);
        }

        $found = count( array_intersect( $langs, $lang_combination ) ) ;
        return $found > 0 ;
    }

}