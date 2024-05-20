<?php

class Engines_Results_DeepL_TranslateResponse extends Engines_Results_AbstractResponse
{
    public $id;
    public $create_date;
    public $segment;
    public $raw_segment;
    public $translation;
    public $source_note;
    public $target_note;
    public $raw_translation;
    public $quality;
    public $reference;
    public $usage_count;
    public $subject;
    public $created_by;
    public $last_updated_by;
    public $last_update_date;
    public $match;
    public $memory_key;
    public $ICE;
    public $tm_properties;
    public $target;
    public $source;

    public function __construct($response){

        $this->responseStatus  = isset( $response[ 'responseStatus' ] ) ? $response[ 'responseStatus' ] : '';
        $this->responseDetails = isset( $response[ 'responseData' ] ) ? $response[ 'responseData' ] : '';
        $this->responseData    = isset( $response[ 'data' ] ) ? $response[ 'data' ] : '';

//        return [
//            'id' => 0,
//            'create_date' => '0000-00-00',
//            'segment' => $_config['segment'],
//            'raw_segment' => $_config['segment'],
//            'translation' => $translation,
//            'source_note' => '',
//            'target_note' => '',
//            'raw_translation' => $translation,
//            'quality' => 85,
//            'reference' => '',
//            'usage_count' => 0,
//            'subject' => '',
//            'created_by' => 'MT-DeepL',
//            'last_updated_by' => '',
//            'last_update_date' => '',
//            'match' => 85,
//            'memory_key' => '',
//            'ICE' => false,
//            'tm_properties' => [],
//            'target' => $_config['target'],
//            'source' => $_config['source'],
//        ];

    }

}