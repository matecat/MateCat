<?php

use Matecat\SubFiltering\MateCatFilter;

class Engines_Results_MyMemory_Matches {

    public $id;
    public $raw_segment;
    public $segment;
    public $translation;
    public $target_note;
    public $raw_translation;
    public $quality;
    public $reference;
    public $usage_count;
    public $subject;
    public $created_by;
    public $last_updated_by;
    public $create_date;
    public $last_update_date;
    public $data;

    public $prop = [];
    public $memory_key;
    public $ICE;
    public $tm_properties;
    public $source_note;

    /**
     * @var FeatureSet
     */
    protected $featureSet;

    public $source;
    public $target;

    public $penalty;

    public $score;

    public $match;

    /**
     * Engines_Results_MyMemory_Matches constructor.
     *
     * @param array $data
     */
    public function __construct( $data = [] ) {
        $this->id               = array_key_exists( 'id', $data ) ? $data[ 'id' ] : '0';
        $this->create_date      = array_key_exists( 'create-date', $data ) ? $data[ 'create-date' ] : '1970-01-01 00:00:00';
        $this->segment          = array_key_exists( 'segment', $data ) ? $data[ 'segment' ] : '';
        $this->raw_segment      = array_key_exists( 'raw_segment', $data ) ? $data[ 'raw_segment' ] : '';
        $this->translation      = array_key_exists( 'translation', $data ) ? $data[ 'translation' ] : '';
        $this->source_note      = array_key_exists( 'source_note', $data ) ? $data[ 'source_note' ] : '';
        $this->target_note      = array_key_exists( 'target_note', $data ) ? $data[ 'target_note' ] : '';
        $this->raw_translation  = array_key_exists( 'raw_translation', $data ) ? $data[ 'raw_translation' ] : '';
        $this->quality          = array_key_exists( 'quality', $data ) ? $data[ 'quality' ] : 0;
        $this->reference        = array_key_exists( 'reference', $data ) ? $data[ 'reference' ] : '';
        $this->usage_count      = array_key_exists( 'usage-count', $data ) ? $data[ 'usage-count' ] : 0;
        $this->subject          = array_key_exists( 'subject', $data ) ? $data[ 'subject' ] : '';
        $this->created_by       = array_key_exists( 'created-by', $data ) ? $data[ 'created-by' ] : '';
        $this->last_updated_by  = array_key_exists( 'last-updated-by', $data ) ? $data[ 'last-updated-by' ] : '';
        $this->last_update_date = array_key_exists( 'last-update-date', $data ) ? $data[ 'last-update-date' ] : '1970-01-01 00:00:00';
        $this->match            = array_key_exists( 'match', $data ) ? $data[ 'match' ] : 0;
        $this->memory_key       = array_key_exists( 'key', $data ) ? $data[ 'key' ] : '';
        $this->ICE              = array_key_exists( 'ICE', $data ) ? (bool)$data[ 'ICE' ] : false;
        $this->tm_properties    = array_key_exists( 'tm_properties', $data ) ? json_decode( $data[ 'tm_properties' ], true ) : [];
        $this->target           = array_key_exists( 'target', $data ) ? $data[ 'target' ] : null;
        $this->source           = array_key_exists( 'source', $data ) ? $data[ 'source' ] : null;
        $this->penalty          = array_key_exists( 'penalty', $data ) ? $data[ 'penalty' ] : null;
        $this->score            = array_key_exists( 'score', $data ) ? $data[ 'score' ] : null;
        $this->prop             = array_key_exists( 'prop', $data ) ? $data[ 'prop' ] : [];
    }

    public function featureSet( FeatureSet $featureSet = null ) {
        $this->featureSet = $featureSet;
    }

    /**
     * @param int   $layerNum
     *
     * @param array $dataRefMap
     * @param null  $source
     * @param null  $target
     *
     * @return array
     * @throws Exception
     */
    public function getMatches( int $layerNum = 2, array $dataRefMap = [], $source = null, $target = null ): array {

        if ( $source and $target ) {
            $this->source = $source;
            $this->target = $target;
        }

        $this->segment         = $this->getLayer( $this->raw_segment, $layerNum, $dataRefMap );
        $this->translation     = $this->getLayer( $this->raw_translation, $layerNum, $dataRefMap );
        $this->raw_segment     = $this->getLayer( $this->raw_segment, 0, $dataRefMap ); //raw_segment must be in layer 0
        $this->raw_translation = $this->getLayer( $this->raw_translation, 0, $dataRefMap ); //raw_translation must be in layer 0

        return $this->toArray();
    }

    /**
     * @param       $string
     * @param       $layerNum
     *
     * @param array $dataRefMap
     *
     * @return mixed
     * @throws Exception
     */
    protected function getLayer( $string, $layerNum, array $dataRefMap = [] ) {

        $featureSet = ( $this->featureSet !== null ) ? $this->featureSet : new FeatureSet();
        /** @var MateCatFilter $filter */
        $filter = MateCatFilter::getInstance( $featureSet, $this->source, $this->target, $dataRefMap );
        switch ( $layerNum ) {
            case 0:
                return $filter->fromLayer1ToLayer0( $string );
            case 1:
                return $string;
            case 2:
                return $filter->fromLayer1ToLayer2( $string );
        }
    }

    /**
     * Returns an array of the public attributes of the struct.
     * If $mask is provided, the resulting array will include
     * only the specified keys.
     *
     * This method is useful in conjunction with PDO execute, where only
     * a subset of the attributes may be required to be bound to the query.
     *
     * @return array
     *
     */
    protected function toArray(): array {

        $attributes       = [];
        $reflectionClass  = new ReflectionObject( $this );
        $publicProperties = $reflectionClass->getProperties( ReflectionProperty::IS_PUBLIC );
        foreach ( $publicProperties as $property ) {
            $attributes[ $property->getName() ] = $property->getValue( $this );
        }

        return $attributes;

    }

}