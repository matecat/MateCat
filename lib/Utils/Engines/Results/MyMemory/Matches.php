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
    protected $_args;

    public $source;
    public $target;

    public function __construct() {

        //NEEDED TO UNIFORM DATA as array( $matches )
        $args = func_get_args();

        if ( empty( $args ) ) {
            throw new Exception( "No args defined for " . __CLASS__ . " constructor" );
        }

        if ( count( $args ) > 1 and is_array( $args[ 0 ] ) ) {
            throw new Exception( "Invalid arg 1 " . __CLASS__ . " constructor" );
        }

        // $args[ 0 ]

        $this->_args = $args;

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

        $match      = [];

        if($source and $target){
            $this->source = $source;
            $this->target = $target;
        }

        if ( count( $this->_args ) == 1 and is_array( $this->_args[ 0 ] ) ) {

            $match = $this->_args[ 0 ];

            if ( $match[ 'last-update-date' ] == "0000-00-00 00:00:00" ) {
                $match[ 'last-update-date' ] = "0000-00-00";
            }
            if ( !empty( $match[ 'last-update-date' ] ) and $match[ 'last-update-date' ] != '0000-00-00' ) {
                $match[ 'last-update-date' ] = date( "Y-m-d", strtotime( $match[ 'last-update-date' ] ) );
            }

            if ( empty( $match[ 'created-by' ] ) ) {
                $match[ 'created-by' ] = "Anonymous";
            }

            $match[ 'match' ] = $match[ 'match' ] * 100;
            $match[ 'match' ] = $match[ 'match' ] . "%";

            ( isset( $match[ 'prop' ] ) ? $match[ 'prop' ] = json_decode( $match[ 'prop' ] ) : $match[ 'prop' ] = [] );

            /* MyMemory Match */
            $match[ 'raw_segment' ]     = $match[ 'segment' ];
            $match[ 'segment' ]         = $this->getLayer( $match[ 'segment' ], $layerNum, $dataRefMap );
            $match[ 'raw_translation' ] = $match[ 'translation' ];
            $match[ 'translation' ]     = $this->getLayer( $match[ 'translation' ], $layerNum, $dataRefMap );

        } elseif ( count( $this->_args ) >= 5 and !is_array( $this->_args[ 0 ] ) ) {
            $match[ 'segment' ]          = $this->getLayer( $this->_args[ 0 ], $layerNum, $dataRefMap );
            $match[ 'raw_segment' ]      = $this->_args[ 0 ];
            $match[ 'translation' ]      = $this->getLayer( $this->_args[ 1 ], $layerNum, $dataRefMap );
            $match[ 'raw_translation' ]  = $this->_args[ 1 ];
            $match[ 'match' ]            = $this->_args[ 2 ];
            $match[ 'created-by' ]       = $this->_args[ 3 ];
            $match[ 'create-date' ]      = $this->_args[ 4 ];
            $match[ 'last-update-date' ] = $this->_args[ 4 ];
            $match[ 'prop' ]             = ( isset( $this->_args[ 5 ] ) ? $this->_args[ 5 ] : [] );
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
        $this->target           = array_key_exists( 'target', $match) ? $match[ 'target' ] : $target;
        $this->source           = array_key_exists( 'source', $match) ? $match[ 'source' ] : $source;

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

        $featureSet = ($this->featureSet !== null) ? $this->featureSet : new FeatureSet();
        $filter = MateCatFilter::getInstance( $featureSet, $this->source, $this->target, $dataRefMap );
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