<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 13/11/23
 * Time: 19:11
 *
 */

namespace API\App\Json\Analysis;

use JsonSerializable;

class AnalysisJobSummary implements MatchContainerInterface, JsonSerializable {

    /**
     * @var AnalysisMatch[]
     */
    protected $matches = [];

    public function __construct() {
        foreach ( MatchConstants::$forValue as $matchType ) {
            $this->matches[ $matchType ] = AnalysisMatch::forName( $matchType );
        }
    }

    public function jsonSerialize() {
        return array_values( $this->matches );
    }

    /**
     * @return AnalysisMatch
     */
    public function getMatch( $matchName ) {
        return $this->matches[ $matchName ];
    }

}