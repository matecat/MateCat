<?php

class ProjectOptionsModel {
    
    private $options ; 
    private $sanitized = array();  
    
    private $source_lang ; 
    private $target_lang ; 
    
    private $lexiQA_allowed_languages = array(
        'en-US',
        'en-GB',
        'fr-FR',
        'de-DE',
        'it-IT'
    ); 
    
    private $tag_projection_allowed_languages = array(
        'en-US',
        'en-GB',
        'it-IT'
    ); 
    
    public function __construct( $input_options ) { 
        $this->options = $input_options ; 
    }
    
    public function setLanguages( $source, $target ) {
        $this->source_lang = $source ; 
        $this->target_lang = $target ; 
    }
    
    public function sanitize() {
        $this->sanitized = array(); 
        
        if ( !isset( $this->options['speech2text'] ) ) {
            $this->sanitized['speech2text'] = TRUE  ; 
        }
        
        $this->sanitizeTagProjection(); 
        $this->sanitizeLexiQA(); 
        
        return $this->sanitized ; 
    }
    
    private function sanitizeLexiQA() { 
        if ( isset($this->options['lexiqa']) && $this->options['lexiqa'] == FALSE ) {
            return ; 
        }
        
        if ( $this->checkSourceAndTargetAreInCombination( $this->tag_projection_allowed_languages ) ) {
            $this->sanitized['lexiqa'] = TRUE;
        }
    }
    
    private function sanitizeTagProjection() {
        if ( isset($this->options['tag_projection']) && $this->options['tag_projection'] == FALSE ) {
            return ; 
        }
        
        if ( $this->checkSourceAndTargetAreInCombination( $this->tag_projection_allowed_languages ) ) {
             $this->sanitized['tag_projection'] = TRUE; 
        }
    }
    
    private function checkSourceAndTargetAreInCombination( $langs ) {
        $all_langs = array_merge( $this->target_lang, array($this->source_lang) ); 
        
        $all_langs = array_unique( $all_langs ) ; 
        
        $found = count( array_intersect( $langs, $all_langs ) ) ;
        return $found >= 1 ; 
    }

}