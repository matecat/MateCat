<?php

namespace Utils\Engines\Results\MyMemory;

use Exception;
use Utils\Engines\Results\TMSAbstractResponse;

class GetMemoryResponse extends TMSAbstractResponse {
    /**
     * @var Matches[]|array
     */
    public array $matches = [];

    public function __construct( $result ) {

        $this->responseData    = $result[ 'responseData' ] ?? '';
        $this->responseDetails = $result[ 'responseDetails' ] ?? '';
        $this->responseStatus  = $result[ 'responseStatus' ] ?? '';
        $this->mtLangSupported = $result[ 'mtLangSupported' ] ?? true;

        if ( is_array( $result ) and !empty( $result ) and array_key_exists( 'matches', $result ) ) {

            $matches = $result[ 'matches' ];
            if ( is_array( $matches ) and !empty( $matches ) ) {

                foreach ( $matches as $match ) {
                    $this->matches[] = $this->buildMyMemoryMatch( $match );
                }
            }
        }
    }

    /**
     * @param $match
     *
     * @return Matches
     */
    private function buildMyMemoryMatch( $match ): Matches {
        if ( $match[ 'last-update-date' ] == "0000-00-00 00:00:00" ) {
            $match[ 'last-update-date' ] = "1970-01-01 00:00:00";
        }

        if ( !empty( $match[ 'last-update-date' ] ) and $match[ 'last-update-date' ] != '0000-00-00' ) {
            $match[ 'last-update-date' ] = date( "Y-m-d", strtotime( $match[ 'last-update-date' ] ) );
        }

        $match[ 'create-date' ] = ( isset( $match[ 'create-date' ] ) and $match[ 'create-date' ] !== "0000-00-00 00:00:00" ) ? date( "Y-m-d H:i:s", strtotime( $match[ 'create-date' ] ) ) : $match[ 'last-update-date' ];

        $match[ 'match' ] = $match[ 'match' ] * 100;
        $match[ 'match' ] = $match[ 'match' ] . "%";

        $match[ 'prop' ] = isset( $match[ 'prop' ] ) ? json_decode( $match[ 'prop' ] ) : [];

        return new Matches( [
                'id'               => $match[ 'id' ] ?? '0',
                'raw_segment'      => $match[ 'segment' ] ?? '',
                'raw_translation'  => $match[ 'translation' ] ?? '',
                'match'            => $match[ 'match' ],
                'created-by'       => $match[ 'created-by' ] ?? "Anonymous",
                'create-date'      => $match[ 'create-date' ] ?? '1970-01-01 00:00:00',
                'prop'             => $match[ 'prop' ] ?? [],
                'quality'          => $match[ 'quality' ] ?? 0,
                'usage-count'      => $match[ 'usage-count' ] ?? 0,
                'subject'          => $match[ 'subject' ] ?? '',
                'reference'        => $match[ 'reference' ] ?? '',
                'last-updated-by'  => $match[ 'last-updated-by' ] ?? '',
                'last-update-date' => $match[ 'last-update-date' ] ?? '1970-01-01 00:00:00',
                'tm_properties'    => $match[ 'tm_properties' ],
                'key'              => $match[ 'key' ] ?? '',
                'ICE'              => $match[ 'ICE' ] ?? false,
                'source_note'      => $match[ 'source_note' ] ?? null,
                'target_note'      => $match[ 'target_note' ] ?? null,
                'penalty'          => $match[ 'penalty' ] ?? null,
        ] );
    }

    /**
     * Get matches as array
     *
     * @param int   $layerNum
     * @param array $dataRefMap
     * @param null  $source
     * @param null  $target
     * @param null  $id_project
     *
     * @return array
     * @throws Exception
     */
    public function get_matches_as_array( int $layerNum = 2, array $dataRefMap = [], $source = null, $target = null, $id_project = null ): array {
        $matchesArray = [];

        foreach ( $this->matches as $match ) {
            $item           = $match->getMatches( $layerNum, $dataRefMap, $source, $target, $id_project );
            $matchesArray[] = $item;
        }

        return $matchesArray;
    }

}