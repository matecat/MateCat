<?php


class ChunkOptionsModel {
    
    private $chunk ; 
    
    public static $valid_keys = array(
        'speech2text', 'tag_projection', 'lexiqa'
    ); 
    
    private $received_options = array() ; 
    
    public function __construct( Chunks_ChunkStruct $chunk) {
        $this->chunk = $chunk ;
        $this->project_metadata = $chunk->getProject()->getMetadataAsKeyValue(); 
    }
    
    public function isEnabled($key) {
        $value = $this->getByChunkOrProjectOption( $key ) ;

        $sanitizer = new ProjectOptionsSanitizer( array( $key => $value ) ) ;
        $sanitizer->setLanguages( $this->chunk->source, $this->chunk->target ) ;

        $sanitized = $sanitizer->sanitize() ; 
        return $sanitized[ $key ] ; 
        
    }
    
    public function setOptions( $options ) { 
        $filtered = array_intersect_key($options, array_flip( self::$valid_keys ) ); 

        $sanitizer = new ProjectOptionsSanitizer( $filtered ) ;
        $sanitizer->setLanguages( $this->chunk->source, $this->chunk->target ) ;
        
        $sanitized = $sanitizer->sanitize() ; 
        
        $this->received_options = array_merge( 
                $filtered,
                $sanitized 
        ); 
    }
    
    public function save() {
        if ( empty( $this->received_options ) ) {
            return ; 
        }
        
        $dao = new Projects_MetadataDao() ; 
        
        foreach( $this->received_options as $key => $value ) {
            $dao->set($this->chunk->id_project, Projects_MetadataDao::buildChunkKey($key, $this->chunk), $value);
        }

        $this->project_metadata = $this->chunk->getProject()->getMetadataAsKeyValue(); 
    }
    
    public function toArray() {
        $out = array() ; 
        
        foreach( static::$valid_keys as $name ) { 
            $out[ $name ] = $this->isEnabled( $name ) ; 
        }
        return $out ; 
    }

    /**
     * @param $key
     *
     * @return bool
     */
    private function getByChunkOrProjectOption( $key ) {
        $chunk_key = Projects_MetadataDao::buildChunkKey($key, $this->chunk ) ;

        if ( isset( $this->project_metadata[ $chunk_key ] ) ) {
            return !!$this->project_metadata[ $chunk_key ] ;
        }
        else if ( isset( $this->project_metadata[ $key ] ) ) {
            return !!$this->project_metadata[ $key ] ;
        }
        else {
            return false ;
        }
    }

}