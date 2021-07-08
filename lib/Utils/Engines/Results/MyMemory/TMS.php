<?php

class Engines_Results_MyMemory_TMS extends Engines_Results_AbstractResponse {
    /**
     * @var Engines_Results_MyMemory_Matches[]|array
     */
    public $matches = array();

    public function __construct( $result ) {


        $this->responseData    = isset( $result[ 'responseData' ] ) ? $result[ 'responseData' ] : '';
        $this->responseDetails = isset( $result[ 'responseDetails' ] ) ? $result[ 'responseDetails' ] : '';
        $this->responseStatus  = isset( $result[ 'responseStatus' ] ) ? $result[ 'responseStatus' ] : '';
        $this->mtLangSupported = ( isset( $result[ 'mtLangSupported' ] ) &&  !is_null( $result[ 'mtLangSupported' ] ) ) ? $result[ 'mtLangSupported' ] : true;

        if ( is_array( $result ) and !empty( $result ) and array_key_exists( 'matches', $result ) ) {

            $matches = $result[ 'matches' ];
            if ( is_array( $matches ) and !empty( $matches ) ) {

                foreach ( $matches as $match ) {
                    $currMatch        = new Engines_Results_MyMemory_Matches( $match );
                    $this->matches[ ] = $currMatch;
                }
            }
        }
    }

    /**
     * Get matches as array
     *
     * @param int   $layerNum
     * @param array $dataRefMap
     * @param null  $source
     * @param null  $target
     *
     * @return array
     * @throws Exception
     */
    public function get_matches_as_array( $layerNum = 2, array $dataRefMap = [], $source = null, $target = null ) {
        $matchesArray = [];

        foreach ( $this->matches as $match ) {
            $item            = $match->getMatches( $layerNum, $dataRefMap, $source, $target );
            $matchesArray[]  = $item;
        }

        return $matchesArray;
    }

    /**
     * Transform one level list to multi level matches based on segment key
     *
     * @return array
     * @throws Exception
     */
    public function get_glossary_matches_as_array() {
        $tmp_vector = array();
        $TMS_RESULT = $this->get_matches_as_array();
        foreach ( $TMS_RESULT as $single_match ) {
            $tmp_vector[ $single_match[ 'segment' ] ][ ] = $single_match;
        }
        $TMS_RESULT = $tmp_vector;

        return $TMS_RESULT;
    }

    public function get_as_array() {
        return (array) $this;
    }

}