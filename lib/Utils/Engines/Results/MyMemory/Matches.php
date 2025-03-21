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
        $this->id = 0;
        $this->raw_segment = $raw_segment;
        $this->raw_translation = $raw_translation;
        $this->match = $match;
        $this->created_by = $createdBy ?? "Anonymous";
        $this->create_date = $createDate ?? "0000-00-00 00:00:00";
        $this->last_update_date = $createDate ?? "0000-00-00 00:00:00";
        $this->score = $score;
        $this->prop = $prop ?? [];
        $this->tm_properties = [];
        $this->ICE = false;
        $this->match = 0;
        $this->quality = 0;
        $this->usage_count = 0;
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

        if ( $source and $target ) {
            $this->source = $source;
            $this->target = $target;
        }

        $this->segment = $this->getLayer( $this->raw_segment, $layerNum, $dataRefMap );
        $this->translation = $this->getLayer( $this->raw_translation, $layerNum, $dataRefMap );

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