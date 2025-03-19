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
    public $match;

    public $prop;
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

    /**
     * Engines_Results_MyMemory_Matches constructor.
     * @param $raw_segment
     * @param $raw_translation
     * @param $match
     * @param $createdBy
     * @param $createDate
     * @param $score
     * @param $prop
     */
    public function __construct(
        $raw_segment,
        $raw_translation,
        $match,
        $createdBy = null,
        $createDate = null,
        $score = null,
        $prop = []
    ) {
        $this->raw_segment = $raw_segment;
        $this->raw_translation = $raw_translation;
        $this->match = $match;
        $this->created_by = $createdBy ?? "Anonymous";
        $this->create_date = $createDate ?? "0000-00-00 00:00:00";
        $this->last_update_date = $createDate ?? "0000-00-00 00:00:00";
        $this->score = $score;
        $this->prop = $prop ?? [];
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
    public function getMatches( $layerNum = 2, array $dataRefMap = [], $source = null, $target = null ) {

        $match = [];

        if ( $source and $target ) {
            $this->source = $source;
            $this->target = $target;
        }

        $match[ 'segment' ]          = $this->getLayer( $this->raw_segment, $layerNum, $dataRefMap );
        $match[ 'raw_segment' ]      = $this->raw_segment;
        $match[ 'translation' ]      = $this->getLayer( $this->raw_translation, $layerNum, $dataRefMap );
        $match[ 'raw_translation' ]  = $this->raw_translation;
        $match[ 'match' ]            = $this->match;

        if(!empty($this->created_by)){
            $match[ 'created-by' ] = $this->created_by;
        }

        if(!empty($this->create_date)){
            $match[ 'create-date' ] = $this->create_date;
        }

        if(!empty($this->last_update_date)){
            $match[ 'last-update-date' ] = $this->last_update_date;
        }

        if(!empty($this->score)){
            $match[ 'score' ] = $this->score;
        }

        if(!empty($this->prop)){
            $match[ 'prop' ] = $this->prop;
        }

        $this->id               = array_key_exists( 'id', $match ) ? $match[ 'id' ] : '0';
        $this->create_date      = array_key_exists( 'create-date', $match ) ? $match[ 'create-date' ] : '0000-00-00';
        $this->segment          = array_key_exists( 'segment', $match ) ? $match[ 'segment' ] : '';
        $this->raw_segment      = array_key_exists( 'raw_segment', $match ) ? $match[ 'raw_segment' ] : '';
        $this->translation      = array_key_exists( 'translation', $match ) ? $match[ 'translation' ] : '';
        $this->source_note      = array_key_exists( 'source_note', $match ) ? $match[ 'source_note' ] : '';
        $this->target_note      = array_key_exists( 'target_note', $match ) ? $match[ 'target_note' ] : '';
        $this->raw_translation  = array_key_exists( 'raw_translation', $match ) ? $match[ 'raw_translation' ] : '';
        $this->quality          = array_key_exists( 'quality', $match ) ? $match[ 'quality' ] : 0;
        $this->reference        = array_key_exists( 'reference', $match ) ? $match[ 'reference' ] : '';
        $this->usage_count      = array_key_exists( 'usage-count', $match ) ? $match[ 'usage-count' ] : 0;
        $this->subject          = array_key_exists( 'subject', $match ) ? $match[ 'subject' ] : '';
        $this->created_by       = array_key_exists( 'created-by', $match ) ? $match[ 'created-by' ] : '';
        $this->last_updated_by  = array_key_exists( 'last-updated-by', $match ) ? $match[ 'last-updated-by' ] : '';
        $this->last_update_date = array_key_exists( 'last-update-date', $match ) ? $match[ 'last-update-date' ] : '0000-00-00';
        $this->match            = array_key_exists( 'match', $match ) ? $match[ 'match' ] : 0;
        $this->memory_key       = array_key_exists( 'key', $match ) ? $match[ 'key' ] : '';
        $this->ICE              = array_key_exists( 'ICE', $match ) ? (bool)$match[ 'ICE' ] : false;
        $this->tm_properties    = array_key_exists( 'tm_properties', $match ) ? json_decode( $match[ 'tm_properties' ], true ) : [];
        $this->target           = array_key_exists( 'target', $match ) ? $match[ 'target' ] : $target;
        $this->source           = array_key_exists( 'source', $match ) ? $match[ 'source' ] : $source;
        $this->penalty          = array_key_exists( 'penalty', $match ) ? $match[ 'penalty' ] : $source;
        $this->score            = array_key_exists( 'score', $match ) ? $match[ 'score' ] : '';

        $this->prop = $match[ 'prop' ];

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
        $filter     = MateCatFilter::getInstance( $featureSet, $this->source, $this->target, $dataRefMap );
        switch ( $layerNum ) {
            case 0:
                return $filter->fromLayer1ToLayer0( $string );
                break;
            case 1:
                return $string;
                break;
            case 2:
                return $filter->fromLayer1ToLayer2( $string );
                break;
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
    protected function toArray() {

        $attributes       = [];
        $reflectionClass  = new ReflectionObject( $this );
        $publicProperties = $reflectionClass->getProperties( ReflectionProperty::IS_PUBLIC );
        foreach ( $publicProperties as $property ) {
            $attributes[ $property->getName() ] = $property->getValue( $this );
        }

        return $attributes;

    }

}