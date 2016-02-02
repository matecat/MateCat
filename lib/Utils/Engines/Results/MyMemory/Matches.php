<?php

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

    public function __construct() {

        //NEEDED TO UNIFORM DATA as array( $matches )
        $args = func_get_args();

        if ( empty( $args ) ) {
            throw new Exception( "No args defined for " . __CLASS__ . " constructor" );
        }

        $match = array();
        if ( count( $args ) == 1 and is_array( $args[ 0 ] ) ) {

            $match = $args[ 0 ];
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

            ( isset( $match[ 'prop' ] ) ? $match[ 'prop' ] = json_decode( $match[ 'prop' ] ) : $match[ 'prop' ] = array() );

        }

        if ( count( $args ) > 1 and is_array( $args[ 0 ] ) ) {
            throw new Exception( "Invalid arg 1 " . __CLASS__ . " constructor" );
        }

        if ( count( $args ) == 5 and !is_array( $args[ 0 ] ) ) {
            $match[ 'segment' ]          = CatUtils::rawxliff2view( $args[ 0 ] );
            $match[ 'raw_segment' ]      = $args[ 0 ];
            $match[ 'translation' ]      = CatUtils::rawxliff2view( $args[ 1 ] );
            $match[ 'raw_translation' ]  = $args[ 1 ];
            $match[ 'match' ]            = $args[ 2 ];
            $match[ 'created-by' ]       = $args[ 3 ];
            $match[ 'last-update-date' ] = $args[ 4 ];
            $match[ 'prop' ]             = ( isset( $args[ 5 ] ) ? $args[ 5 ] : array() );
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

        $this->prop             = $match[ 'prop' ];

    }

    public function get_as_array() {
        return ((array) $this);
    }

}