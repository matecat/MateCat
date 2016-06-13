<?php

class ProjectOptionsSanitizer {
    
    private $options ; 
    private $sanitized = array();  
    
    private $source_lang ; 
    private $target_lang ; 
    
    public static $lexiQA_allowed_languages = array(
        'en-US',
        'en-GB',
        'fr-FR',
        'de-DE',
        'it-IT'
    ); 
    
    public static $tag_projection_allowed_languages = array(
        'en-US',
        'en-GB',
        'it-IT'
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
        
        if ( !isset( $this->options['speech2text'] ) ) {
            $this->sanitized['speech2text'] = TRUE  ; 
        }
        else {
            $this->sanitized['speech2text'] = !!$this->options['speech2text']; 
        }
        
        $this->sanitizeTagProjection(); 
        $this->sanitizeLexiQA(); 
        
        $this->forceInt(); 
        
        return $this->sanitized ; 
    }
    
    private function forceInt() {
        foreach( $this->sanitized as $key => $value ) {
            $this->sanitized[ $key ] = intval( $value ) ;
        }
    }
    
    private function sanitizeLexiQA() { 
        if ( isset($this->options['lexiqa']) && $this->options['lexiqa'] == FALSE ) {
            $this->sanitized['lexiqa'] = FALSE;
        }
        else if ( $this->checkSourceAndTargetAreInCombination( self::$lexiQA_allowed_languages ) ) {
            $this->sanitized['lexiqa'] = TRUE;
        }
        else {
            $this->sanitized['lexiqa'] = FALSE;
        }
    }
    
    private function sanitizeTagProjection() {
        if ( isset($this->options['tag_projection']) && $this->options['tag_projection'] == FALSE ) {
            $this->sanitized['tag_projection'] = FALSE; 
        }
        else if ( $this->checkSourceAndTargetAreInCombination( self::$tag_projection_allowed_languages ) ) {
             $this->sanitized['tag_projection'] = TRUE; 
        }
        else {
            $this->sanitized['tag_projection'] = FALSE; 
        }
    }
    
    private function checkSourceAndTargetAreInCombination( $langs ) {
        $all_langs = array_merge( $this->target_lang, array($this->source_lang) ); 
        
        $all_langs = array_unique( $all_langs ) ; 
        
        $found = count( array_intersect( $langs, $all_langs ) ) ;
        return $found >= 1 ; 
    }

}