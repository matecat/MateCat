<?php

class Engines_Results_MyMemory_TMS extends Engines_Results_AbstractResponse {
    /**
     * @var Engines_Results_MyMemory_Matches[]|array
     */
    public $matches = [];

    public function __construct( $result ) {

        $this->responseData    = isset( $result[ 'responseData' ] ) ? $result[ 'responseData' ] : '';
        $this->responseDetails = isset( $result[ 'responseDetails' ] ) ? $result[ 'responseDetails' ] : '';
        $this->responseStatus  = isset( $result[ 'responseStatus' ] ) ? $result[ 'responseStatus' ] : '';
        $this->mtLangSupported = ( isset( $result[ 'mtLangSupported' ] ) && !is_null( $result[ 'mtLangSupported' ] ) ) ? $result[ 'mtLangSupported' ] : true;

        if ( is_array( $result ) and !empty( $result ) and array_key_exists( 'matches', $result ) ) {

            $matches = $result[ 'matches' ];
            if ( is_array( $matches ) and !empty( $matches ) ) {

                foreach ( $matches as $match ) {
                    $this->matches[] = $this->buildMyMemoryMatch($match);
                }
            }
        }
    }

    /**
     * @param $match
     * @return Engines_Results_MyMemory_Matches
     */
    private function buildMyMemoryMatch($match)
    {
        if ( $match[ 'last-update-date' ] == "0000-00-00 00:00:00" ) {
            $match[ 'last-update-date' ] = "1970-01-01 00:00:00";
        }

        if ( !empty( $match[ 'last-update-date' ] ) and $match[ 'last-update-date' ] != '0000-00-00' ) {
            $match[ 'last-update-date' ] = date( "Y-m-d", strtotime( $match[ 'last-update-date' ] ) );
        }

        $match['create-date'] =  (isset($match['create-date']) and $match['create-date'] !== "0000-00-00 00:00:00") ? date( "Y-m-d H:i:s", strtotime( $match[ 'create-date' ] ) ) : $match[ 'last-update-date' ];

        $match[ 'match' ] = $match[ 'match' ] * 100;
        $match[ 'match' ] = $match[ 'match' ] . "%";

        $match[ 'prop' ] = isset( $match[ 'prop' ]) ? json_decode( $match[ 'prop' ]) : [];

        return new Engines_Results_MyMemory_Matches([
            'id' => $match['id'] ?? '0',
            'raw_segment' => $match['segment'] ?? '',
            'raw_translation' => $match['translation'] ?? '',
            'match' => $match['match'],
            'created-by' => $match['created-by'] ?? "Anonymous",
            'create-date' => $match['create-date'] ?? '1970-01-01 00:00:00',
            'prop' => $match['prop'] ?? [],
            'quality' => $match['quality'] ?? 0,
            'usage-count' => $match['usage-count'] ?? 0,
            'subject' => $match['subject'] ?? '',
            'reference' => $match['reference'] ?? '',
            'last-updated-by' => $match['last-updated-by'] ?? '',
            'last-update-date' => $match['last-update-date'] ?? '1970-01-01 00:00:00',
            'tm_properties' => $match['tm_properties'],
            'key' => $match['key'] ?? '',
        ]);
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
            $item           = $match->getMatches( $layerNum, $dataRefMap, $source, $target );
            $matchesArray[] = $item;
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
        $tmp_vector = [];
        $TMS_RESULT = $this->get_matches_as_array();
        foreach ( $TMS_RESULT as $single_match ) {
            $tmp_vector[ $single_match[ 'segment' ] ][] = $single_match;
        }
        $TMS_RESULT = $tmp_vector;

        return $TMS_RESULT;
    }

    public function get_as_array() {
        return (array)$this;
    }

}